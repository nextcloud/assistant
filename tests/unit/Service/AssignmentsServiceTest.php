<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\BackgroundJob\RunAssignmentsJob;
use OCA\Assistant\Db\Assignment;
use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\BadRequestException;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\InternalException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\IDateTimeZone;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AssignmentsServiceTest extends TestCase {
	// 2026-01-01T00:00:00Z
	private const STARTS_AT = 1767225600;

	private AssignmentMapper&MockObject $assignmentMapper;
	private SessionMapper&MockObject $sessionMapper;
	private ChatService&MockObject $chatService;
	private IJobList&MockObject $jobList;
	private IDBConnection&MockObject $db;
	private AssignmentsService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->chatService = $this->createMock(ChatService::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->db = $this->createMock(IDBConnection::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
		$this->service = new AssignmentsService(
			$this->assignmentMapper,
			$this->sessionMapper,
			$this->chatService,
			$timeFactory,
			$this->createMock(LoggerInterface::class),
			$this->jobList,
			$this->createMock(IL10N::class),
			$this->createMock(IDateTimeZone::class),
			$this->createMock(IUserManager::class),
			$this->db,
		);
	}

	public static function dataInvalidTitle(): array {
		return [
			['', 'The title must not be empty'],
			[" \t\n", 'The title must not be empty'],
			[str_repeat('ä', 257), 'The title must be at most 256 characters'],
		];
	}

	/**
	 * @dataProvider dataInvalidTitle
	 */
	public function testCreateRejectsInvalidTitle(string $title, string $error): void {
		$this->assignmentMapper->expects(self::never())->method('insert');
		$this->chatService->expects(self::never())->method('createChatSession');

		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage($error);
		$this->create($title);
	}

	/**
	 * @return array{assignment: Assignment, sessionId: int}
	 */
	private function create(string $title): array {
		return $this->service->createAssignment('alice', $title, 'say hi', self::STARTS_AT, 'FREQ=DAILY', 'UTC');
	}

	private static function session(int $id): Session {
		$session = new Session();
		$session->setId($id);
		return $session;
	}

	public function testCreateStoresAllInOneTransaction(): void {
		$this->assignmentMapper->expects(self::once())->method('insert')
			->willReturnCallback(static function (Assignment $assignment): Assignment {
				$assignment->setId(1);
				return $assignment;
			});
		$this->chatService->expects(self::once())->method('createChatSession')
			->with('alice', null, 'Daily news')
			->willReturn(self::session(10));
		$this->sessionMapper->expects(self::once())->method('update')
			->with(self::callback(static fn (Session $session): bool => $session->getAssignmentId() === 1))
			->willReturnArgument(0);
		$this->jobList->method('has')->willReturn(false);
		$this->jobList->expects(self::once())->method('add')->with(RunAssignmentsJob::class, ['userId' => 'alice']);
		$this->db->expects(self::once())->method('beginTransaction');
		$this->db->expects(self::once())->method('commit');
		$this->db->expects(self::never())->method('rollBack');

		$result = $this->create('Daily news');

		self::assertSame(1, $result['assignment']->getId());
		self::assertSame(10, $result['sessionId']);
	}

	public function testCreateAcceptsTitleOfMaxLength(): void {
		$title = str_repeat('ä', 256);
		$this->assignmentMapper->method('insert')->willReturnArgument(0);
		$this->chatService->expects(self::once())->method('createChatSession')
			->with('alice', null, $title)
			->willReturn(self::session(10));

		$result = $this->create($title);

		self::assertSame(10, $result['sessionId']);
	}

	public function testCreateTrimsTitle(): void {
		$this->assignmentMapper->method('insert')->willReturnArgument(0);
		$this->chatService->expects(self::once())->method('createChatSession')
			->with('alice', null, 'Daily news')
			->willReturn(self::session(10));

		$this->create(" Daily news \n");
	}

	public function testCreateRollsBackWhenSessionIsNotCreated(): void {
		$this->assignmentMapper->method('insert')->willReturnArgument(0);
		$this->chatService->method('createChatSession')->willThrowException(new InternalException());
		$this->expectRollBack();

		$this->expectException(InternalException::class);
		$this->create('Daily news');
	}

	public function testCreateRollsBackWhenSessionIsNotLinked(): void {
		$this->assignmentMapper->method('insert')->willReturnArgument(0);
		$this->chatService->method('createChatSession')->willReturn(self::session(10));
		$this->sessionMapper->method('update')->willThrowException(new Exception('db error'));
		$this->expectRollBack();

		$this->expectException(InternalException::class);
		$this->create('Daily news');
	}

	private function expectRollBack(): void {
		$this->jobList->expects(self::never())->method('add');
		$this->db->expects(self::once())->method('beginTransaction');
		$this->db->expects(self::never())->method('commit');
		$this->db->expects(self::once())->method('rollBack');
	}
}
