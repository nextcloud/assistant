<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Listener;

use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Listener\ChattyLLMTaskListener;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Concurrent task processing executors can run the same task twice,
 * which must not store the chat message twice.
 * Any user can schedule a task with the custom ID of a chat session of another user,
 * which must not write to that session.
 */
class ChattyLLMTaskListenerTest extends TestCase {

	private const SESSION_ID = 23;
	private const TASK_ID = 91;

	private MessageMapper&MockObject $messageMapper;
	private SessionMapper&MockObject $sessionMapper;
	private ChatService&MockObject $chatService;
	private NotificationService&MockObject $notificationService;
	private LoggerInterface&MockObject $logger;
	private ChattyLLMTaskListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->messageMapper = $this->createMock(MessageMapper::class);
		$this->chatService = $this->createMock(ChatService::class);
		$this->notificationService = $this->createMock(NotificationService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$session = new Session();
		$session->setId(self::SESSION_ID);
		$session->setUserId('admin');
		$session->setAssignmentId(null);
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		// the session belongs to admin, the lookup is scoped to the user like the real one
		$this->sessionMapper->method('getUserSession')
			->willReturnCallback(static function (string $userId, int $sessionId) use ($session): Session {
				if ($userId !== $session->getUserId() || $sessionId !== $session->getId()) {
					throw new DoesNotExistException('Session not found');
				}
				return $session;
			});

		$this->listener = new ChattyLLMTaskListener(
			$this->messageMapper,
			$this->sessionMapper,
			$this->createMock(TaskProcessingService::class),
			$this->logger,
			$this->notificationService,
			$this->chatService,
		);
	}

	private function makeTask(): Task {
		$task = new Task(
			TextToTextChat::ID,
			[
				'input' => 'What is a cat?',
				'system_prompt' => '',
				'history' => [],
			],
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput(['output' => 'A cat is an animal.']);
		return $task;
	}

	private function handleTask(): void {
		$event = new TaskSuccessfulEvent($this->makeTask());
		$this->listener->handle($event);
	}

	public function testMessageIsStored(): void {
		$this->messageMapper->method('getMessageByTaskId')
			->with(self::SESSION_ID, self::TASK_ID)
			->willThrowException(new DoesNotExistException('Message not found'));
		$storedMessage = null;
		$this->messageMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (Message $message) use (&$storedMessage): Message {
				$storedMessage = $message;
				$message->setId(3);
				return $message;
			});
		$this->sessionMapper->expects($this->once())->method('update');

		$this->handleTask();

		$this->assertNotNull($storedMessage);
		$this->assertSame(self::SESSION_ID, $storedMessage->getSessionId());
		$this->assertSame(self::TASK_ID, $storedMessage->getOcpTaskId());
		$this->assertSame(Message::ROLE_ASSISTANT, $storedMessage->getRole());
		$this->assertSame('A cat is an animal.', $storedMessage->getContent());
	}

	public function testMessageIsStoredOnlyOnce(): void {
		$storedMessage = new Message();
		$storedMessage->setSessionId(self::SESSION_ID);
		$storedMessage->setOcpTaskId(self::TASK_ID);
		$this->messageMapper->method('getMessageByTaskId')
			->with(self::SESSION_ID, self::TASK_ID)
			->willReturn($storedMessage);
		$this->messageMapper->expects($this->never())->method('insert');
		$this->sessionMapper->expects($this->never())->method('update');

		$this->handleTask();
	}

	public function testTitleTasksAreUntouched(): void {
		$task = new Task(
			TextToTextChat::ID,
			[
				'input' => 'Generate a title',
				'system_prompt' => '',
				'history' => [],
			],
			'assistant:chatty-llm',
			'admin',
			'chatty-title:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput(['output' => "\"Cats\"\nmore text"]);

		$event = new TaskSuccessfulEvent($task);
		$this->messageMapper->expects($this->never())->method('getMessageByTaskId');
		$this->messageMapper->expects($this->never())->method('insert');
		$this->chatService->expects($this->once())
			->method('cleanGeneratedTitle')
			->with('admin', "\"Cats\"\nmore text")
			->willReturn('Cats');
		$this->sessionMapper->expects($this->once())
			->method('updateSessionTitle')
			->with('admin', self::SESSION_ID, 'Cats');

		$this->listener->handle($event);
	}

	private function makeOtherUsersTask(string $taskTypeId, string $customId, array $output): Task {
		$task = new Task($taskTypeId, ['input' => 'anything'], 'assistant:chatty-llm', 'attacker', $customId);
		$task->setId(self::TASK_ID);
		$task->setOutput($output);
		return $task;
	}

	public function testMessageForAnotherUsersSessionIsNotStored(): void {
		$this->logger->expects($this->once())
			->method('info')
			->with($this->anything(), $this->callback(static function (array $context): bool {
				return $context['userId'] === 'attacker' && $context['sessionId'] === self::SESSION_ID;
			}));
		$this->messageMapper->expects($this->never())->method('getMessageByTaskId');
		$this->messageMapper->expects($this->never())->method('insert');
		$this->messageMapper->expects($this->never())->method('update');
		$this->sessionMapper->expects($this->never())->method('update');

		$task = $this->makeOtherUsersTask(TextToTextChat::ID, 'chatty-llm:' . self::SESSION_ID, ['output' => 'Sign in again at https://attacker.example']);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	public function testAudioMessageOfAnotherUsersSessionIsNotRewritten(): void {
		$this->messageMapper->method('getMessageByTaskId')
			->willThrowException(new DoesNotExistException('Message not found'));
		$this->messageMapper->expects($this->never())->method('getMessageById');
		$this->messageMapper->expects($this->never())->method('update');
		$this->messageMapper->expects($this->never())->method('insert');
		$this->sessionMapper->expects($this->never())->method('updateSessionTitle');

		$task = $this->makeOtherUsersTask(
			\OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID,
			'chatty-llm:' . self::SESSION_ID . ':7',
			['output' => 1, 'output_transcript' => 'reply', 'input_transcript' => 'words the victim never said'],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	public function testTitleOfAnotherUsersSessionIsNotSet(): void {
		$this->chatService->method('cleanGeneratedTitle')->willReturn('Renamed');
		$this->sessionMapper->expects($this->never())->method('updateSessionTitle');

		$task = $this->makeOtherUsersTask(TextToTextChat::ID, 'chatty-title:' . self::SESSION_ID, ['output' => 'Renamed']);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	public function testFailedTaskOfAnotherUsersSessionIsIgnored(): void {
		$this->notificationService->expects($this->never())->method('sendAssignmentNotification');

		$task = $this->makeOtherUsersTask(TextToTextChat::ID, 'chatty-llm:' . self::SESSION_ID, []);
		$this->listener->handle(new TaskFailedEvent($task, 'failed'));
	}

	/**
	 * The event is dispatched in the task processing worker, which stops if the listener throws
	 */
	public function testErrorsDoNotLeaveTheListener(): void {
		$this->messageMapper->method('getMessageByTaskId')
			->willThrowException(new DoesNotExistException('Message not found'));
		$this->messageMapper->method('getMessageById')
			->willThrowException(new DoesNotExistException('Message not found'));
		$this->logger->expects($this->once())->method('error');

		$task = new Task(
			\OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID,
			['input' => 1],
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID . ':999999',
		);
		$task->setId(self::TASK_ID);
		$task->setOutput(['output' => 1, 'output_transcript' => 'reply', 'input_transcript' => 'question']);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}
}
