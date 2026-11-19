<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Controller;

use OCA\Assistant\Controller\AssignmentsApiController;
use OCA\Assistant\Db\Assignment;
use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AssignmentsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AssignmentsApiControllerTest extends TestCase {

	private AssignmentMapper&MockObject $assignmentMapper;
	private SessionMapper&MockObject $sessionMapper;
	private AssignmentsService&MockObject $assignmentsService;
	private AssignmentsApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->assignmentsService = $this->createMock(AssignmentsService::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00Z'));

		$this->controller = new AssignmentsApiController(
			'assistant',
			$this->createMock(IRequest::class),
			'alice',
			$this->assignmentMapper,
			$this->createMock(LoggerInterface::class),
			$timeFactory,
			$this->assignmentsService,
			$this->sessionMapper,
		);
	}

	private static function assignment(int $id): Assignment {
		return Assignment::fromRow([
			'id' => $id,
			'user_id' => 'alice',
			'prompt' => 'say hi',
			'recurrence' => 'FREQ=DAILY',
			'starts_at' => 1767225600,
			'created_at' => 1767225600,
			'updated_at' => 1767225600,
			'last_run_at' => 0,
			'timezone' => 'UTC',
		]);
	}

	public function testListReturnsSessionId(): void {
		$this->assignmentMapper->method('findForUser')->with('alice')
			->willReturnCallback(static fn () => yield from [self::assignment(1), self::assignment(2), self::assignment(3)]);
		$this->sessionMapper->expects(self::once())->method('getSessionIdsForAssignments')
			->with('alice', [1, 2, 3])
			->willReturn([2 => 20, 1 => 10]);

		$response = $this->controller->getUserAssignments();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$assignments = $response->getData()['assignments'];
		self::assertSame([10, 20, null], array_column($assignments, 'session_id'));
		self::assertSame('say hi', $assignments[0]['prompt']);
	}

	public function testGetReturnsSessionId(): void {
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->method('getSessionIdsForAssignments')->with('alice', [1])
			->willReturn([1 => 10]);

		$response = $this->controller->getUserAssignment(1);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(10, $response->getData()['assignment']['session_id']);
	}

	public function testCreateReturnsSessionId(): void {
		$this->assignmentsService->method('createAssignment')
			->with('alice', 'Daily news', 'say hi', 1767225600, 'FREQ=DAILY', null)
			->willReturn(self::assignment(1));
		$this->sessionMapper->method('getSessionIdsForAssignments')->with('alice', [1])
			->willReturn([1 => 10]);

		$response = $this->controller->createUserAssignment('Daily news', 'say hi', 1767225600, 'FREQ=DAILY', null);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(10, $response->getData()['assignment']['session_id']);
	}

	public function testUpdateReturnsSessionId(): void {
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->method('getSessionIdsForAssignments')->with('alice', [1])
			->willReturn([1 => 10]);

		$response = $this->controller->updateUserAssignment(1, 'new prompt', null, null, null);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(10, $response->getData()['assignment']['session_id']);
		self::assertSame('new prompt', $response->getData()['assignment']['prompt']);
	}
}
