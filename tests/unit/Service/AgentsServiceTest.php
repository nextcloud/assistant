<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\AgentMapper;
use OCA\Assistant\Service\AgentsService;
use OCA\Assistant\Service\AgentUserException;
use OCA\Assistant\Service\AgentUserService;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AgentsServiceTest extends TestCase {

	private AgentMapper|MockObject $agentMapper;
	private AgentUserService&MockObject $agentUserService;
	private IUserManager&MockObject $userManager;
	private LoggerInterface&MockObject $logger;
	private AgentsService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->agentMapper = $this->createMock(AgentMapper::class);
		$this->agentUserService = $this->createMock(AgentUserService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new AgentsService(
			$this->agentMapper,
			$this->agentUserService,
			$this->userManager,
			$this->logger,
		);
	}

	public function testCreateAgentProvisionsTheUserAndStoresTheAgent(): void {
		$agentUser = $this->createMock(IUser::class);
		$this->agentUserService->expects($this->once())
			->method('createAgentUser')
			->with('Code Agent', 'code_agent');

		$stored = null;
		$this->agentMapper->method('insert')
			->willReturnCallback(static function ($agent) use (&$stored) {
				$agent->setId(7);
				$stored = $agent;
				return $agent;
			});

		$agent = $this->service->createAgent('Code Agent', 'code_agent', 'Be nice.', 'tool:code');
		$this->assertSame('code_agent', $agent->getUserId());
		$this->assertSame('Be nice.', $agent->getPersonality());
		$this->assertSame('tool:code', $agent->getScope());
		$this->assertSame(7, $agent->getId());
	}

	public function testCreateAgentFailsIfUserProvisioningFails(): void {
		$this->agentUserService->expects($this->once())
			->method('createAgentUser')
			->willThrowException(new AgentUserException('taken'));
		$this->agentMapper->expects($this->never())->method('insert');

		$this->expectException(AgentUserException::class);
		$this->service->createAgent('Code Agent', 'code_agent', 'Be nice.', '');
	}

	public function testDeleteAgentWithoutUserDeletion(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentMapper->expects($this->once())->method('deleteAgent')->with($agent);
		$this->userManager->expects($this->never())->method('get');

		$this->service->deleteAgent(5, false);
	}

	public function testDeleteAgentWithUserDeletion(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentMapper->expects($this->once())->method('deleteAgent')->with($agent);

		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('delete');
		$this->userManager->expects($this->once())->method('get')->with('agent1')->willReturn($user);

		$this->service->deleteAgent(5, true);
	}

	public function testDeleteAgentKeepsTheUserIfDeleteFails(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentMapper->expects($this->once())->method('deleteAgent')
			->willThrowException(new \OCP\DB\Exception('db down'));
		$this->userManager->expects($this->never())->method('get');

		$this->expectException(\OCP\DB\Exception::class);
		$this->service->deleteAgent(5, true);
	}

	public function testUpdateAgent() {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentMapper->expects($this->once())->method('update')
			->willReturnCallback(static function ($updated) {
				return $updated;
			});

		$updated = $this->service->updateAgent(5, personality: 'New personality');
		$this->assertSame('New personality', $updated->getPersonality());
	}

	private function createAgentEntity(int $id, string $userId): \OCA\Assistant\Db\Agent {
		$agent = new \OCA\Assistant\Db\Agent();
		$agent->setId($id);
		$agent->setUserId($userId);
		$agent->setPersonality('Be nice.');
		return $agent;
	}
}
