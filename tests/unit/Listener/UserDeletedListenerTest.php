<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Listener;

use OCA\Assistant\Listener\UserDeletedListener;
use OCA\Assistant\Service\AgentsService;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\InternalException;
use OCA\Assistant\Service\SessionSummaryService;
use OCP\DB\Exception;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * When a user (regular or agent) is deleted outside of this app,
 * all assistant data of that user must be cleaned up.
 */
class UserDeletedListenerTest extends TestCase {

	private ChatService&MockObject $chatService;
	private AssignmentsService&MockObject $assignmentsService;
	private SessionSummaryService&MockObject $sessionSummaryService;
	private AgentsService&MockObject $agentsService;
	private LoggerInterface&MockObject $logger;
	private UserDeletedListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->chatService = $this->createMock(ChatService::class);
		$this->assignmentsService = $this->createMock(AssignmentsService::class);
		$this->sessionSummaryService = $this->createMock(SessionSummaryService::class);
		$this->agentsService = $this->createMock(AgentsService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new UserDeletedListener(
			$this->chatService,
			$this->assignmentsService,
			$this->logger,
			$this->sessionSummaryService,
			$this->agentsService,
		);
	}

	private function handleEvent(string $userId): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUid')->willReturn($userId);
		$this->listener->handle(new UserDeletedEvent($user));
	}

	public function testAllUserDataIsDeleted(): void {
		$this->assignmentsService->expects($this->once())
			->method('deleteAllForUser')
			->with('agent1');
		$this->chatService->expects($this->once())
			->method('deleteAllUserChatData')
			->with('agent1');
		$this->sessionSummaryService->expects($this->once())
			->method('deleteSummaryJobsForUser')
			->with('agent1');
		$this->agentsService->expects($this->once())
			->method('deleteAgentForUser')
			->with('agent1');

		$this->handleEvent('agent1');
	}

	public function testAssignmentErrorDoesNotStopOtherCleanups(): void {
		$this->assignmentsService->expects($this->once())
			->method('deleteAllForUser')
			->willThrowException(new InternalException('DB error'));
		$this->chatService->expects($this->once())->method('deleteAllUserChatData');
		$this->sessionSummaryService->expects($this->once())->method('deleteSummaryJobsForUser');
		$this->agentsService->expects($this->once())->method('deleteAgentForUser');
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('assignments'), $this->anything());

		$this->handleEvent('user1');
	}

	public function testAgentErrorDoesNotStopOtherCleanups(): void {
		$this->agentsService->expects($this->once())
			->method('deleteAgentForUser')
			->willThrowException(new Exception('DB error'));
		$this->assignmentsService->expects($this->once())->method('deleteAllForUser');
		$this->chatService->expects($this->once())->method('deleteAllUserChatData');
		$this->sessionSummaryService->expects($this->once())->method('deleteSummaryJobsForUser');
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('agent data'), $this->anything());

		$this->handleEvent('user1');
	}

	public function testOtherEventsAreIgnored(): void {
		$event = new \OCP\EventDispatcher\GenericEvent();
		$this->assignmentsService->expects($this->never())->method('deleteAllForUser');
		$this->chatService->expects($this->never())->method('deleteAllUserChatData');
		$this->sessionSummaryService->expects($this->never())->method('deleteSummaryJobsForUser');
		$this->agentsService->expects($this->never())->method('deleteAgentForUser');

		$this->listener->handle($event);
	}
}
