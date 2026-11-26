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
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * The requester of scheduled tasks ordered through an agent in Talk is
 * recorded on the assignments (option B of the assignments plan), together
 * with the Talk thread that carries the run results.
 */
class AssignmentsServiceTest extends TestCase {

	private const AGENT_USER_ID = 'agent_user';
	private const REQUESTER_USER_ID = 'alice';
	private const ROOM_TOKEN = 'room-token';
	private const THREAD_ID = 777;

	private AssignmentMapper&MockObject $assignmentMapper;
	private SessionMapper&MockObject $sessionMapper;
	private AgentTalkService&MockObject $agentTalkService;
	private AssignmentsService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->agentTalkService = $this->createMock(AgentTalkService::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => str_replace('%s', (string)($parameters[0] ?? ''), $text)
		);
		$this->agentTalkService->method('getUserL10n')->willReturn($l10n);

		$this->service = new AssignmentsService(
			$this->assignmentMapper,
			$this->sessionMapper,
			$this->createMock(ChatService::class),
			$this->agentTalkService,
			$this->createMock(ITimeFactory::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IJobList::class),
			$this->createMock(IDateTimeZone::class),
			$this->createMock(IUserManager::class),
		);
	}

	private function makeSession(): Session {
		$session = new Session();
		$session->setId(42);
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

	public function testLinkRequesterStoresTheRequesterAndTheRoom(): void {
		$session = $this->makeSession();
		$assignment = $this->makeAssignment(5);
		$this->assignmentMapper->method('find')
			->with(self::AGENT_USER_ID, 5)
			->willReturn($assignment);

		$updated = null;
		$this->assignmentMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static function (Assignment $assignment) use (&$updated): Assignment {
				$updated = $assignment;
				return $assignment;
			});

		$this->service->linkRequesterToCreatedScheduledTasks($session, [5]);

		$this->assertNotNull($updated);
		$this->assertSame(self::REQUESTER_USER_ID, $updated->getRequesterUserId());
	}

	public function testLinkRequesterIgnoresAssignmentsOfOtherUsers(): void {
		$session = $this->makeSession();
		// find() is scoped by the agent user id: assignments of other users
		// are not returned at all
		$this->assignmentMapper->method('find')
			->willThrowException(new DoesNotExistException('assignment belongs to another user'));
		$this->assignmentMapper->expects($this->never())->method('update');

		$this->service->linkRequesterToCreatedScheduledTasks($session, [5]);
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
