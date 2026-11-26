<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\BackgroundJob;

use OCA\Assistant\BackgroundJob\AgentPresenceJob;
use OCA\Assistant\Db\Agent;
use OCA\Assistant\Service\AgentsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IUserStatus;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AgentPresenceJobTest extends TestCase {

	private AgentsService&MockObject $agentsService;
	private IUserManager&MockObject $userManager;
	private IEventDispatcher&MockObject $eventDispatcher;
	private LoggerInterface&MockObject $logger;
	private AgentPresenceJob $job;

	protected function setUp(): void {
		parent::setUp();
		$this->agentsService = $this->createMock(AgentsService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->job = new AgentPresenceJob(
			$this->createMock(ITimeFactory::class),
			$this->agentsService,
			$this->userManager,
			$this->eventDispatcher,
			$this->logger,
		);
	}

	private function createAgent(string $userId): Agent {
		$agent = new Agent();
		$agent->setId(1);
		$agent->setUserId($userId);
		return $agent;
	}

	private function runJob(): void {
		$this->invokePrivate($this->job, 'run', [null]);
	}

	public function testDispatchesALiveStatusEventForEveryAgentUser(): void {
		$this->agentsService->method('getAllAgents')
			->willReturn([
				$this->createAgent('bob'),
				$this->createAgent('agent2'),
			]);
		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$agent2 = $this->createMock(IUser::class);
		$agent2->method('getUID')->willReturn('agent2');
		$this->userManager->method('get')
			->willReturnCallback(static fn (string $userId) => $userId === 'bob' ? $bob : $agent2);

		$events = [];
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(static function (object $event) use (&$events) {
				$events[] = $event;
			});

		$this->runJob();

		$this->assertCount(2, $events);
		$this->assertInstanceOf(UserLiveStatusEvent::class, $events[0]);
		$this->assertSame('bob', $events[0]->getUser()->getUID());
		$this->assertSame(IUserStatus::ONLINE, $events[0]->getStatus());
		$this->assertSame('agent2', $events[1]->getUser()->getUID());
	}

	public function testSkipsAgentUsersThatDoNotExistAnymore(): void {
		$this->agentsService->method('getAllAgents')
			->willReturn([$this->createAgent('ghost')]);
		$this->userManager->method('get')->with('ghost')->willReturn(null);
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->runJob();
	}

	public function testKeepsGoingWhenTheAgentListCannotBeLoaded(): void {
		$this->agentsService->method('getAllAgents')
			->willThrowException(new \Exception('db down'));
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->once())->method('warning');

		$this->runJob();
	}

	public function testKeepsGoingWhenADispatchFails(): void {
		$this->agentsService->method('getAllAgents')
			->willReturn([
				$this->createAgent('bob'),
				$this->createAgent('agent2'),
			]);
		$bob = $this->createMock(IUser::class);
		$agent2 = $this->createMock(IUser::class);
		$agent2->method('getUID')->willReturn('agent2');
		$this->userManager->method('get')
			->willReturnCallback(static fn (string $userId) => $userId === 'bob' ? $bob : $agent2);

		$this->eventDispatcher->expects($this->exactly(2))
			->method('dispatchTyped')
			->willReturnCallback(static function (object $event) use ($bob): void {
				if ($event->getUser() === $bob) {
					throw new \Exception('dispatch failed');
				}
			});
		$this->logger->expects($this->once())->method('warning');

		$this->runJob();
	}
}
