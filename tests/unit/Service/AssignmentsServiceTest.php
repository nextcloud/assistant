<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Migration\Version040101Date20261125120000;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\ChatService;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Server;
use Test\TestCase;

/**
 * Runs against a real Nextcloud test server with a real database.
 *
 * @group DB
 */
class AssignmentsServiceTest extends TestCase {

	private const TEST_USER = 'assignments_service_test_user';
	private const OTHER_USER = 'assignments_service_other_user';

	private AssignmentsService $service;
	private AssignmentMapper $assignmentMapper;
	private SessionMapper $sessionMapper;
	private bool $createdUser = false;

	protected function setUp(): void {
		parent::setUp();

		$userManager = Server::get(IUserManager::class);
		if (!$userManager->userExists(self::TEST_USER)) {
			$userManager->createUser(self::TEST_USER, self::TEST_USER . '_password123');
			$this->createdUser = true;
		}

		$this->service = Server::get(AssignmentsService::class);
		$this->assignmentMapper = Server::get(AssignmentMapper::class);
		$this->sessionMapper = Server::get(SessionMapper::class);
	}

	protected function tearDown(): void {
		$this->service->deleteAllForUser(self::TEST_USER);
		Server::get(ChatService::class)->deleteAllUserChatData(self::TEST_USER);
		if ($this->createdUser) {
			Server::get(IUserManager::class)->get(self::TEST_USER)?->delete();
		}
		parent::tearDown();
	}

	/**
	 * @return list<int>
	 */
	private function sessionIds(bool $isAssignment): array {
		return array_map(static fn (Session $session) => $session->getId(), $this->sessionMapper->getUserSessions(self::TEST_USER, $isAssignment));
	}

	public function testDeleteAssignmentMovesItsSessionToTheNormalChats(): void {
		$assignment = $this->service->createAssignment(self::TEST_USER, 'Daily digest', 'Summarize my day', time(), 'FREQ=DAILY', 'UTC');
		$session = $this->sessionMapper->getUserSessionForAssignment(self::TEST_USER, $assignment->getId());
		$this->assertContains($session->getId(), $this->sessionIds(true));

		$this->service->deleteAssignment($assignment);

		$this->assertFalse($this->assignmentMapper->exists(self::TEST_USER, $assignment->getId()));
		$this->assertNotContains($session->getId(), $this->sessionIds(true));
		$this->assertContains($session->getId(), $this->sessionIds(false));
		$this->assertSame('Daily digest', $this->sessionMapper->getUserSession(self::TEST_USER, $session->getId())->getTitle());
	}

	public function testDeleteSessionsDeletesTheirAssignments(): void {
		$deleted = $this->service->createAssignment(self::TEST_USER, 'Deleted', 'Prompt', time(), 'FREQ=DAILY', 'UTC');
		$kept = $this->service->createAssignment(self::TEST_USER, 'Kept', 'Prompt', time(), 'FREQ=DAILY', 'UTC');
		$session = $this->sessionMapper->getUserSessionForAssignment(self::TEST_USER, $deleted->getId());

		Server::get(ChatService::class)->deleteSessions(self::TEST_USER, [$session->getId()]);

		$this->assertFalse($this->assignmentMapper->exists(self::TEST_USER, $deleted->getId()));
		$this->assertTrue($this->assignmentMapper->exists(self::TEST_USER, $kept->getId()));
	}

	public function testDeleteSessionDeletesOnlyTheOwnersAssignment(): void {
		$assignment = $this->service->createAssignment(self::TEST_USER, 'Daily digest', 'Prompt', time(), 'FREQ=DAILY', 'UTC');
		$session = $this->sessionMapper->getUserSessionForAssignment(self::TEST_USER, $assignment->getId());
		$chatService = Server::get(ChatService::class);

		$chatService->deleteSession(self::OTHER_USER, $session->getId());
		$this->assertTrue($this->assignmentMapper->exists(self::TEST_USER, $assignment->getId()));

		$chatService->deleteSession(self::TEST_USER, $session->getId());
		$this->assertFalse($this->assignmentMapper->exists(self::TEST_USER, $assignment->getId()));
	}

	public function testRepairDetachesOnlySessionsOfDeletedAssignments(): void {
		$kept = $this->service->createAssignment(self::TEST_USER, 'Kept', 'Prompt', time(), 'FREQ=DAILY', 'UTC');
		$deleted = $this->service->createAssignment(self::TEST_USER, 'Deleted', 'Prompt', time(), 'FREQ=DAILY', 'UTC');
		$keptSession = $this->sessionMapper->getUserSessionForAssignment(self::TEST_USER, $kept->getId());
		$orphanedSession = $this->sessionMapper->getUserSessionForAssignment(self::TEST_USER, $deleted->getId());
		// what deleting an assignment did before sessions were detached
		$this->assignmentMapper->delete($deleted);

		Server::get(Version040101Date20261125120000::class)
			->postSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		$this->assertSame([$keptSession->getId()], $this->sessionIds(true));
		$this->assertContains($orphanedSession->getId(), $this->sessionIds(false));
	}
}
