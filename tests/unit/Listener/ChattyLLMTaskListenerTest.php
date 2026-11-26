<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Listener;

use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Listener\ChattyLLMTaskListener;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Any user can schedule a task with the custom ID of a chat session of another user,
 * which must not write to that session.
 */
class ChattyLLMTaskListenerTest extends TestCase {

	private const SESSION_ID = 23;
	private const TASK_ID = 91;

	private MessageMapper&MockObject $messageMapper;
	private SessionMapper&MockObject $sessionMapper;
	private LoggerInterface&MockObject $logger;
	private ChattyLLMTaskListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->messageMapper = $this->createMock(MessageMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$session = new Session();
		$session->setId(self::SESSION_ID);
		$session->setUserId('admin');
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
		);
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
		if (!class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat')) {
			$this->markTestSkipped('AudioToAudioChat task type requires Nextcloud 32+');
		}
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
		$this->sessionMapper->expects($this->never())->method('updateSessionTitle');

		$task = $this->makeOtherUsersTask(TextToTextChat::ID, 'chatty-title:' . self::SESSION_ID, ['output' => 'Renamed']);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	/**
	 * The event is dispatched in the task processing worker, which stops if the listener throws
	 */
	public function testErrorsDoNotLeaveTheListener(): void {
		if (!class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat')) {
			$this->markTestSkipped('AudioToAudioChat task type requires Nextcloud 32+');
		}
		$this->messageMapper->method('getMessageByTaskId')
			->willThrowException(new DoesNotExistException('Message not found'));
		$this->messageMapper->method('getMessageById')
			->willThrowException(new DoesNotExistException('Message not found'));
		$loggedException = null;
		$this->logger->expects($this->once())
			->method('error')
			->willReturnCallback(static function (string $message, array $context) use (&$loggedException): void {
				$loggedException = $context['exception'] ?? null;
			});

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

		$this->assertInstanceOf(DoesNotExistException::class, $loggedException);
	}
}
