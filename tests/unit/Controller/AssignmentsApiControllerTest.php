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
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AssignmentsService;
use OCP\AppFramework\Db\DoesNotExistException;
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

	private static function session(int $id, int $assignmentId, ?string $title): Session {
		$session = new Session();
		$session->setId($id);
		$session->setUserId('alice');
		$session->setAssignmentId($assignmentId);
		$session->setTitle($title);
		return $session;
	}

	public function testListReturnsTitleAndSessionId(): void {
		$this->assignmentMapper->method('findForUser')->with('alice')
			->willReturnCallback(static fn () => yield from [self::assignment(1), self::assignment(2), self::assignment(3)]);
		$this->sessionMapper->expects(self::once())->method('getUserSessionsForAssignments')
			->with('alice', [1, 2, 3])
			->willReturn([self::session(20, 2, 'Second'), self::session(10, 1, 'First')]);

		$response = $this->controller->getUserAssignments();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$assignments = $response->getData()['assignments'];
		self::assertSame(['First', 'Second', null], array_column($assignments, 'title'));
		self::assertSame([10, 20, null], array_column($assignments, 'session_id'));
		self::assertSame('say hi', $assignments[0]['prompt']);
	}

	public function testGetReturnsTitleAndSessionId(): void {
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->method('getUserSessionsForAssignments')->with('alice', [1])
			->willReturn([self::session(10, 1, 'Daily news')]);

		$response = $this->controller->getUserAssignment(1);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame('Daily news', $response->getData()['assignment']['title']);
		self::assertSame(10, $response->getData()['assignment']['session_id']);
	}

	public function testCreateReturnsTitleAndSessionId(): void {
		$this->assignmentsService->method('createAssignment')
			->with('alice', 'Daily news', 'say hi', 1767225600, 'FREQ=DAILY', null)
			->willReturn(self::assignment(1));
		$this->sessionMapper->method('getUserSessionsForAssignments')->with('alice', [1])
			->willReturn([self::session(10, 1, 'Daily news')]);

		$response = $this->controller->createUserAssignment('Daily news', 'say hi', 1767225600, 'FREQ=DAILY', null);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame('Daily news', $response->getData()['assignment']['title']);
		self::assertSame(10, $response->getData()['assignment']['session_id']);
	}

	public function testUpdateRenamesSession(): void {
		$session = self::session(10, 1, 'Old title');
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->method('getUserSessionForAssignment')->with('alice', 1)->willReturn($session);
		$this->sessionMapper->expects(self::once())->method('update')
			->with(self::callback(static fn (Session $updated): bool => $updated->getTitle() === 'New title'));
		$this->sessionMapper->method('getUserSessionsForAssignments')->willReturn([$session]);

		$response = $this->controller->updateUserAssignment(1, null, null, null, null, 'New title');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame('New title', $response->getData()['assignment']['title']);
	}

	public function testUpdateWithoutTitleKeepsSession(): void {
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->expects(self::never())->method('getUserSessionForAssignment');
		$this->sessionMapper->expects(self::never())->method('update');
		$this->sessionMapper->method('getUserSessionsForAssignments')
			->willReturn([self::session(10, 1, 'Old title')]);

		$response = $this->controller->updateUserAssignment(1, 'new prompt', null, null, null);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame('Old title', $response->getData()['assignment']['title']);
		self::assertSame('new prompt', $response->getData()['assignment']['prompt']);
	}

	public function testUpdateDoesNotRenameSessionWhenRuleIsInvalid(): void {
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->method('getUserSessionForAssignment')->willReturn(self::session(10, 1, 'Old title'));
		$this->sessionMapper->expects(self::never())->method('update');
		$this->assignmentMapper->expects(self::never())->method('update');

		$response = $this->controller->updateUserAssignment(1, null, 'FREQ=NOPE', null, null, 'New title');

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUpdateRejectsTooLongTitle(): void {
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->expects(self::never())->method('update');
		$this->assignmentMapper->expects(self::never())->method('update');

		$response = $this->controller->updateUserAssignment(1, null, null, null, null, str_repeat('ä', 257));

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['error' => 'The title must be at most 256 characters'], $response->getData());
	}

	public function testUpdateAcceptsTitleOfMaxLength(): void {
		$title = str_repeat('ä', 256);
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->method('getUserSessionForAssignment')->willReturn(self::session(10, 1, 'Old title'));
		$this->sessionMapper->expects(self::once())->method('update');
		$this->sessionMapper->method('getUserSessionsForAssignments')->willReturn([self::session(10, 1, $title)]);

		$response = $this->controller->updateUserAssignment(1, null, null, null, null, $title);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateTitleWithoutSessionReturnsNotFound(): void {
		$this->assignmentMapper->method('find')->with('alice', 1)->willReturn(self::assignment(1));
		$this->sessionMapper->method('getUserSessionForAssignment')->willThrowException(new DoesNotExistException(''));
		$this->assignmentMapper->expects(self::never())->method('update');

		$response = $this->controller->updateUserAssignment(1, null, null, null, null, 'New title');

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
