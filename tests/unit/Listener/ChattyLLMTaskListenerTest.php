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
use OCA\Assistant\Service\AgentTalkService;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction;
use OCP\TaskProcessing\TaskTypes\ContextAgentInteraction;
use OCP\TaskProcessing\TaskTypes\MultimodalContextAgentInteraction;
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

		$this->rebuildListener($this->createMock(AgentTalkService::class));
	}

	private function rebuildListener(
		AgentTalkService $agentTalkService,
		?SessionMapper $sessionMapper = null,
		?NotificationService $notificationService = null,
		?AssignmentsService $assignmentsService = null,
	): void {
		$this->listener = new ChattyLLMTaskListener(
			$this->messageMapper,
			$sessionMapper ?? $this->sessionMapper,
			$this->createMock(TaskProcessingService::class),
			$agentTalkService,
			$assignmentsService ?? $this->createMock(AssignmentsService::class),
			$this->logger,
			$notificationService ?? $this->notificationService,
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

	private function makeAudioTask(array $input, array $output): Task {
		$task = new Task(
			ContextAgentAudioInteraction::ID,
			$input,
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput($output);
		return $task;
	}

	private function makeOtherUsersTask(string $taskTypeId, string $customId, array $output): Task {
		$task = new Task($taskTypeId, ['input' => 'anything'], 'assistant:chatty-llm', 'attacker', $customId);
		$task->setId(self::TASK_ID);
		$task->setOutput($output);
		return $task;
	}

	private function givenUnstoredMessage(): void {
		$this->messageMapper->method('getMessageByTaskId')
			->with(self::SESSION_ID, self::TASK_ID)
			->willThrowException(new DoesNotExistException('Message not found'));
		$this->messageMapper->method('insert')
			->willReturnCallback(static function (Message $message): Message {
				$message->setId(3);
				return $message;
			});
	}

	private function givenAgentSession(SessionMapper&MockObject $sessionMapper, Session $session): void {
		$sessionMapper->method('getUserSession')->willReturn($session);
		$sessionMapper->method('update');
	}

	public function testAgentReplyReceivesTheAudioTranscript(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = new Session();
		$session->setAssignmentId(null);
		$session->setRequesterUserId('alice');
		$this->givenAgentSession($sessionMapper, $session);

		$this->givenUnstoredMessage();

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				'The agent answer',
				null,
				'Hello agent, what do you hear?',
				456,
				[],
			);
		$this->rebuildListener($agentTalkService, $sessionMapper);

		$task = $this->makeAudioTask(
			['input' => 123, 'confirmation' => 0, 'conversation_token' => ''],
			[
				'output' => 456,
				'output_transcript' => 'The agent answer',
				'input_transcript' => 'Hello agent, what do you hear?',
			],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	public function testAgentReplyReceivesNoTranscriptOnConfirmationFollowUp(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = new Session();
		$session->setAssignmentId(null);
		$session->setRequesterUserId('alice');
		$this->givenAgentSession($sessionMapper, $session);

		$this->givenUnstoredMessage();

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				'The agent answer',
				null,
				null,
				456,
				[],
			);
		$this->rebuildListener($agentTalkService, $sessionMapper);

		$task = $this->makeAudioTask(
			['input' => 123, 'confirmation' => 1, 'conversation_token' => 'token'],
			[
				'output' => 456,
				'output_transcript' => 'The agent answer',
				'input_transcript' => 'Hello agent, what do you hear?',
			],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	public function testAgentReplyReceivesTheMultimodalOutputFiles(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = new Session();
		$session->setAssignmentId(null);
		$session->setRequesterUserId('alice');
		$this->givenAgentSession($sessionMapper, $session);

		$this->givenUnstoredMessage();

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				'Here is your image.',
				null,
				null,
				null,
				[789, 790],
			);
		$this->rebuildListener($agentTalkService, $sessionMapper);

		$task = new Task(
			MultimodalContextAgentInteraction::ID,
			[
				'input' => 'Generate an image of a cat',
				'confirmation' => 0,
				'conversation_token' => '',
				'input_attachments' => [],
			],
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput([
			'output' => 'Here is your image.',
			'output_attachments' => [789, 790],
		]);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	private function makeAgencyTask(array $input, array $output): Task {
		$task = new Task(
			ContextAgentInteraction::ID,
			$input,
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput($output);
		return $task;
	}

	private function makeRunSession(): Session {
		$session = new Session();
		$session->setAssignmentId(9);
		$session->setRequesterUserId('alice');
		return $session;
	}

	/**
	 * Each run message of a recurring task carries the time of the run, and
	 * the thread it is posted in is stored on the task (also when the run had
	 * to recreate a deleted thread).
	 */
	public function testAssignmentRunOutputCarriesTheRunHeader(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = $this->makeRunSession();
		$this->givenAgentSession($sessionMapper, $session);
		$this->givenUnstoredMessage();

		$promptMessage = new Message();
		$promptMessage->setTimestamp(1730000000);
		$this->messageMapper->method('getLastHumanMessage')->willReturn($promptMessage);

		/** @var AssignmentsService&MockObject $assignmentsService */
		$assignmentsService = $this->createMock(AssignmentsService::class);
		$assignmentsService->expects($this->once())
			->method('storeScheduledTaskThread')
			->with($session, [9]);

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->once())
			->method('getAssignmentRunHeader')
			->with(1730000000, 'alice')
			->willReturn('**Run of Mon 24 Nov 2026, 09:00**');
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				"**Run of Mon 24 Nov 2026, 09:00**\n\nThe result",
				null,
				null,
				null,
				[],
			)
			->willReturnCallback(static function () use ($session): void {
				$session->setThreadId(777);
			});
		$this->rebuildListener($agentTalkService, $sessionMapper, null, $assignmentsService);

		$task = $this->makeAgencyTask(
			['input' => 'Make the weekly report', 'confirmation' => 0, 'conversation_token' => ''],
			[
				'output' => 'The result',
				'actions' => '',
				'conversation_token' => 'new-token',
			],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	/**
	 * The follow-up of a confirmation inside a run continues the same run
	 * turn: it carries no run header.
	 */
	public function testAssignmentRunConfirmationFollowUpHasNoRunHeader(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = $this->makeRunSession();
		$this->givenAgentSession($sessionMapper, $session);
		$this->givenUnstoredMessage();

		/** @var AssignmentsService&MockObject $assignmentsService */
		$assignmentsService = $this->createMock(AssignmentsService::class);
		$assignmentsService->expects($this->never())->method('storeScheduledTaskThread');

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->never())->method('getAssignmentRunHeader');
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				'The follow-up answer',
				'',
				null,
				null,
				[],
			);
		$this->rebuildListener($agentTalkService, $sessionMapper);

		$task = $this->makeAgencyTask(
			['input' => 'Go ahead', 'confirmation' => 1, 'conversation_token' => 'token'],
			[
				'output' => 'The follow-up answer',
				'actions' => '',
				'conversation_token' => 'new-token',
			],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	/**
	 * Assignments ordered through an agent in Talk are not notified: their run
	 * output is delivered in the task's Talk thread instead.
	 */
	public function testTalkDeliveredAssignmentRunIsNotNotified(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = $this->makeRunSession();
		$this->givenAgentSession($sessionMapper, $session);
		$this->givenUnstoredMessage();

		/** @var NotificationService&MockObject $notificationService */
		$notificationService = $this->createMock(NotificationService::class);
		$notificationService->expects($this->never())->method('sendAssignmentNotification');

		$this->rebuildListener($this->createMock(AgentTalkService::class), $sessionMapper, $notificationService);

		$task = $this->makeAgencyTask(
			['input' => 'Make the weekly report', 'confirmation' => 0, 'conversation_token' => ''],
			['output' => 'The result', 'actions' => '', 'conversation_token' => 'new-token'],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	/**
	 * Web-created assignments keep being notified: their runs have no Talk
	 * delivery path.
	 */
	public function testWebCreatedAssignmentRunIsNotified(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = new Session();
		$session->setAssignmentId(9);
		$session->setRequesterUserId(null);
		$this->givenAgentSession($sessionMapper, $session);
		$this->givenUnstoredMessage();

		/** @var NotificationService&MockObject $notificationService */
		$notificationService = $this->createMock(NotificationService::class);
		$notificationService->expects($this->once())
			->method('sendAssignmentNotification');

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->never())->method('postAgentTalkReply');

		$this->rebuildListener($agentTalkService, $sessionMapper, $notificationService);

		$task = $this->makeAgencyTask(
			['input' => 'Make the weekly report', 'confirmation' => 0, 'conversation_token' => ''],
			['output' => 'The result', 'actions' => '', 'conversation_token' => 'new-token'],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
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

	public function testFailedTaskOfAnotherAppIsIgnored(): void {
		$this->sessionMapper->expects($this->never())->method('getUserSession');
		$this->notificationService->expects($this->never())->method('sendAssignmentNotification');

		$task = new Task(TextToTextChat::ID, ['input' => 'anything'], 'someotherapp:sometask', 'admin', 'chatty-llm:' . self::SESSION_ID);
		$task->setId(self::TASK_ID);
		$this->listener->handle(new TaskFailedEvent($task, 'failed'));
	}

	public function testFailedTaskWithoutCustomIdIsIgnored(): void {
		$this->sessionMapper->expects($this->never())->method('getUserSession');
		$this->notificationService->expects($this->never())->method('sendAssignmentNotification');

		$task = new Task(TextToTextChat::ID, ['input' => 'anything'], 'assistant:chatty-llm', 'admin', null);
		$task->setId(self::TASK_ID);
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
