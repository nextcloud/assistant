<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\Assignment;
use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AgentTalkService;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\ChatService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\TaskProcessing\Exception\NotFoundException as TaskNotFoundException;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\ContextAgentInteraction;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * The requester of scheduled tasks ordered through an agent in Talk is
 * recorded on the assignments at creation time: the agent run passes the id
 * of the TaskProcessing task it runs in as originTaskId and the requester is
 * resolved from the matching agent session, together with the Talk thread
 * that carries the run results.
 */
class AssignmentsServiceTest extends TestCase {

	private const AGENT_USER_ID = 'agent_user';
	private const REQUESTER_USER_ID = 'alice';
	private const ROOM_TOKEN = 'room-token';
	private const THREAD_ID = 777;
	private const SESSION_ID = 42;

	private AssignmentMapper&MockObject $assignmentMapper;
	private SessionMapper&MockObject $sessionMapper;
	private AgentTalkService&MockObject $agentTalkService;
	private ChatService&MockObject $chatService;
	private ITaskProcessingManager&MockObject $taskProcessingManager;
	private AssignmentsService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->agentTalkService = $this->createMock(AgentTalkService::class);
		$this->chatService = $this->createMock(ChatService::class);
		$this->taskProcessingManager = $this->createMock(ITaskProcessingManager::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => str_replace('%s', (string)($parameters[0] ?? ''), $text)
		);
		$this->agentTalkService->method('getUserL10n')->willReturn($l10n);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new \DateTimeImmutable());

		$this->service = new AssignmentsService(
			$this->assignmentMapper,
			$this->sessionMapper,
			$this->chatService,
			$this->agentTalkService,
			$timeFactory,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IJobList::class),
			$this->createMock(IDateTimeZone::class),
			$this->createMock(IUserManager::class),
			$this->taskProcessingManager,
		);
	}

	private function makeSession(): Session {
		$session = new Session();
		$session->setId(self::SESSION_ID);
		$session->setUserId(self::AGENT_USER_ID);
		$session->setRequesterUserId(self::REQUESTER_USER_ID);
		$session->setRoomToken(self::ROOM_TOKEN);
		return $session;
	}

	private function makeAssignment(int $id): Assignment {
		$assignment = new Assignment();
		$assignment->setId($id);
		$assignment->setUserId(self::AGENT_USER_ID);
		$assignment->setPrompt('Make the report');
		$assignment->setRecurrence('');
		$assignment->setTimezone('UTC');
		return $assignment;
	}

	private function makeOriginTask(string $userId, ?string $customId, array $input = []): Task {
		$task = new Task(
			ContextAgentInteraction::ID,
			$input,
			'assistant:chatty-llm',
			$userId,
			$customId,
		);
		$task->setId(1234);
		return $task;
	}

	/**
	 * The origin task of a chat interaction carries the session id in its
	 * custom id: the requester of the matching agent session is stamped on the
	 * new assignment.
	 */
	public function testCreateAssignmentWithOriginTaskStoresTheRequester(): void {
		$this->taskProcessingManager->method('getTask')
			->with(1234)
			->willReturn($this->makeOriginTask(self::AGENT_USER_ID, 'chatty-llm:' . self::SESSION_ID));
		$this->sessionMapper->method('getUserSession')
			->with(self::AGENT_USER_ID, self::SESSION_ID)
			->willReturn($this->makeSession());

		$inserted = null;
		$this->assignmentMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (Assignment $assignment) use (&$inserted): Assignment {
				$assignment->setId(5);
				$inserted = $assignment;
				return $assignment;
			});
		$this->chatService->method('createChatSession')->willReturn(new Session());

		$this->service->createAssignment(self::AGENT_USER_ID, 'title', 'prompt', 1730000000, '', 'UTC', 1234);

		$this->assertNotNull($inserted);
		$this->assertSame(self::REQUESTER_USER_ID, $inserted->getRequesterUserId());
	}

	/**
	 * Voice interactions run as an agency sub-task without a custom id: the
	 * conversation token in its input identifies the agent session.
	 */
	public function testCreateAssignmentWithVoiceOriginTaskResolvesTheSessionByToken(): void {
		$conversationToken = 'the-conversation-token';
		$this->taskProcessingManager->method('getTask')
			->with(1234)
			->willReturn($this->makeOriginTask(self::AGENT_USER_ID, null, ['conversation_token' => $conversationToken]));
		$this->sessionMapper->method('getAgentSessionByAgencyConversationToken')
			->with(self::AGENT_USER_ID, $conversationToken)
			->willReturn($this->makeSession());
		$this->sessionMapper->method('getUserSession')
			->with(self::AGENT_USER_ID, self::SESSION_ID)
			->willReturn($this->makeSession());

		$inserted = null;
		$this->assignmentMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (Assignment $assignment) use (&$inserted): Assignment {
				$assignment->setId(5);
				$inserted = $assignment;
				return $assignment;
			});
		$this->chatService->method('createChatSession')->willReturn(new Session());

		$this->service->createAssignment(self::AGENT_USER_ID, 'title', 'prompt', 1730000000, '', 'UTC', 1234);

		$this->assertNotNull($inserted);
		$this->assertSame(self::REQUESTER_USER_ID, $inserted->getRequesterUserId());
	}

	/**
	 * An origin task of another user must not leak a requester: the requester
	 * is only resolved from tasks of the agent user creating the assignment.
	 */
	public function testCreateAssignmentWithOriginTaskOfAnotherUserIsNotLinked(): void {
		$this->taskProcessingManager->method('getTask')
			->with(1234)
			->willReturn($this->makeOriginTask('mallory', 'chatty-llm:' . self::SESSION_ID));
		$this->sessionMapper->expects($this->never())->method('getUserSession');

		$inserted = null;
		$this->assignmentMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (Assignment $assignment) use (&$inserted): Assignment {
				$assignment->setId(5);
				$inserted = $assignment;
				return $assignment;
			});
		$this->chatService->method('createChatSession')->willReturn(new Session());

		$this->service->createAssignment(self::AGENT_USER_ID, 'title', 'prompt', 1730000000, '', 'UTC', 1234);

		$this->assertNotNull($inserted);
		$this->assertNull($inserted->getRequesterUserId());
	}

	/**
	 * Tasks that are no agency interaction tasks (e.g. a title generation task)
	 * are not accepted as origin tasks.
	 */
	public function testCreateAssignmentWithNonAgencyOriginTaskIsNotLinked(): void {
		$task = new Task(
			'core:text2text:summary',
			['input' => 'text'],
			'assistant:chatty-llm',
			self::AGENT_USER_ID,
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(1234);
		$this->taskProcessingManager->method('getTask')
			->with(1234)
			->willReturn($task);
		$this->sessionMapper->expects($this->never())->method('getUserSession');

		$inserted = null;
		$this->assignmentMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (Assignment $assignment) use (&$inserted): Assignment {
				$assignment->setId(5);
				$inserted = $assignment;
				return $assignment;
			});
		$this->chatService->method('createChatSession')->willReturn(new Session());

		$this->service->createAssignment(self::AGENT_USER_ID, 'title', 'prompt', 1730000000, '', 'UTC', 1234);

		$this->assertNotNull($inserted);
		$this->assertNull($inserted->getRequesterUserId());
	}

	public function testCreateAssignmentWithUnknownOriginTaskIsNotLinked(): void {
		$this->taskProcessingManager->method('getTask')
			->with(1234)
			->willThrowException(new TaskNotFoundException('task not found'));

		$inserted = null;
		$this->assignmentMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (Assignment $assignment) use (&$inserted): Assignment {
				$assignment->setId(5);
				$inserted = $assignment;
				return $assignment;
			});
		$this->chatService->method('createChatSession')->willReturn(new Session());

		$this->service->createAssignment(self::AGENT_USER_ID, 'title', 'prompt', 1730000000, '', 'UTC', 1234);

		$this->assertNotNull($inserted);
		$this->assertNull($inserted->getRequesterUserId());
	}

	/**
	 * The Talk thread carrying the run results is named after the task, with
	 * the "Scheduled task" prefix like the agent session titles; the generic
	 * name is the fallback when the chat session holding the name is gone.
	 */
	public function testScheduledTaskThreadTitle(): void {
		$assignmentSession = new Session();
		$assignmentSession->setTitle('Weekly report');
		$this->sessionMapper->method('getUserSessionForAssignment')
			->willReturn($assignmentSession);

		$method = new \ReflectionMethod($this->service, 'getScheduledTaskThreadTitle');
		$this->assertSame('Scheduled task - Weekly report', $method->invoke($this->service, $this->makeAssignment(5)));

		$this->sessionMapper->method('getUserSessionForAssignment')
			->willThrowException(new DoesNotExistException('Session not found'));
		$this->assertSame('Scheduled task', $method->invoke($this->service, $this->makeAssignment(5)));
	}

	public function testStoreScheduledTaskThreadStoresTheThreadOnTheAssignment(): void {
		$session = $this->makeSession();
		$session->setThreadId(self::THREAD_ID);
		$assignment = $this->makeAssignment(9);
		$this->assignmentMapper->method('find')
			->with(self::AGENT_USER_ID, 9)
			->willReturn($assignment);

		$updated = null;
		$this->assignmentMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static function (Assignment $assignment) use (&$updated): Assignment {
				$updated = $assignment;
				return $assignment;
			});

		$this->service->storeScheduledTaskThread($session, [9]);

		$this->assertNotNull($updated);
		$this->assertSame(self::THREAD_ID, $updated->getTalkThreadId());
	}

	public function testStoreScheduledTaskThreadSkipsUnchangedThreads(): void {
		$session = $this->makeSession();
		$session->setThreadId(self::THREAD_ID);
		$assignment = $this->makeAssignment(9);
		$assignment->setTalkThreadId(self::THREAD_ID);
		$this->assignmentMapper->method('find')->willReturn($assignment);
		$this->assignmentMapper->expects($this->never())->method('update');

		$this->service->storeScheduledTaskThread($session, [9]);
	}

	public function testStoreScheduledTaskThreadWithoutThreadDoesNothing(): void {
		$session = $this->makeSession();
		$this->assignmentMapper->expects($this->never())->method('find');

		$this->service->storeScheduledTaskThread($session, [9]);
	}
}
