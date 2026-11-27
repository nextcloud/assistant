<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\AgentMapper;
use OCA\Assistant\Service\AgentsService;
use OCA\Assistant\Service\AgentStatusService;
use OCA\Assistant\Service\AgentUserException;
use OCA\Assistant\Service\AgentUserService;
use OCP\DB\Exception;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AgentsServiceTest extends TestCase {

	private AgentMapper|MockObject $agentMapper;
	private AgentUserService&MockObject $agentUserService;
	private AgentStatusService&MockObject $agentStatusService;
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private LoggerInterface&MockObject $logger;
	private IL10N&MockObject $l10n;
	private AgentsService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->agentMapper = $this->createMock(AgentMapper::class);
		$this->agentUserService = $this->createMock(AgentUserService::class);
		$this->agentStatusService = $this->createMock(AgentStatusService::class);
		$this->agentStatusService->method('getDefaultStatusMessage')
			->willReturn('Default status');
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => str_replace('%s', (string)($parameters[0] ?? ''), $text)
		);

		$this->service = new AgentsService(
			$this->agentMapper,
			$this->agentUserService,
			$this->agentStatusService,
			$this->userManager,
			$this->groupManager,
			$this->logger,
			$this->l10n,
		);
	}

	public function testCreateAgentProvisionsTheUserAndStoresTheAgent(): void {
		$this->agentUserService->expects($this->once())
			->method('createAgentUser')
			->with('Code Agent', 'code_agent');
		$this->agentStatusService->expects($this->once())
			->method('setAgentStatus')
			->with('code_agent', 'Default status');

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

	public function testCreateAgentWithAccessList(): void {
		$this->agentUserService->expects($this->once())
			->method('createAgentUser');
		$this->agentStatusService->expects($this->once())
			->method('setAgentStatus')
			->with('code_agent', 'Default status');

		$this->agentMapper->method('insert')
			->willReturnCallback(static function ($agent) {
				$agent->setId(7);
				return $agent;
			});
		$this->agentMapper->expects($this->once())
			->method('setAcl')
			->with(7, [
				['type' => 'user', 'target' => 'user1'],
				['type' => 'group', 'target' => 'group1'],
			]);

		$this->service->createAgent('Code Agent', 'code_agent', '', '', ['user1'], ['group1']);
	}

	public function testCreateAgentWithoutAccessListStoresNoAcl(): void {
		$this->agentUserService->expects($this->once())
			->method('createAgentUser');
		$this->agentStatusService->expects($this->once())
			->method('setAgentStatus')
			->with('code_agent', 'Default status');
		$this->agentMapper->method('insert')
			->willReturnCallback(static function ($agent) {
				$agent->setId(7);
				return $agent;
			});
		$this->agentMapper->expects($this->never())
			->method('setAcl');

		$this->service->createAgent('Code Agent', 'code_agent', '', '');
	}

	public function testCreateAgentFailsIfUserProvisioningFails(): void {
		$this->agentUserService->expects($this->once())
			->method('createAgentUser')
			->willThrowException(new AgentUserException('taken'));
		$this->agentMapper->expects($this->never())->method('insert');

		$this->expectException(AgentUserException::class);
		$this->service->createAgent('Code Agent', 'code_agent', 'Be nice.', '');
	}

	/**
	 * When storing the agent fails after its NC user account was created, the
	 * account must not be left orphaned: the agent row and the user are
	 * deleted again.
	 */
	public function testCreateAgentRollsBackWhenStoringTheAgentFails(): void {
		$this->agentUserService->expects($this->once())->method('createAgentUser');
		$this->agentMapper->method('insert')->willThrowException(new Exception('database error'));
		// no agent row to delete when the insert itself failed
		$this->agentMapper->expects($this->once())->method('deleteAgent');
		$this->agentUserService->expects($this->once())
			->method('deleteAgentUser')
			->with('code_agent');

		$this->expectException(Exception::class);
		$this->service->createAgent('Code Agent', 'code_agent', 'Be nice.', '');
	}

	/**
	 * When the access control or the status could not be applied after the
	 * agent was stored, the half-created agent is deleted again: the admin
	 * gets an error and can retry with a clean state.
	 */
	public function testCreateAgentRollsBackWhenTheAccessControlFails(): void {
		$this->agentUserService->expects($this->once())->method('createAgentUser');
		$this->agentMapper->method('insert')->willReturnCallback(static function ($agent) {
			$agent->setId(7);
			return $agent;
		});
		$this->agentMapper->method('setAcl')->willThrowException(new Exception('database error'));
		$this->agentMapper->expects($this->once())
			->method('deleteAgent')
			->willReturnCallback(static function (Agent $agent): void {
				// the half-created agent row is the one deleted
				self::assertSame(7, $agent->getId());
			});
		$this->agentUserService->expects($this->once())->method('deleteAgentUser');

		$this->expectException(Exception::class);
		$this->service->createAgent('Code Agent', 'code_agent', '', '', ['user1']);
	}

	public function testCreateAgentAppliesTheGivenStatus(): void {
		$this->agentUserService->expects($this->once())
			->method('createAgentUser');
		$this->agentStatusService->expects($this->once())
			->method('setAgentStatus')
			->with('code_agent', 'Busy until tomorrow');
		$this->agentMapper->method('insert')
			->willReturnCallback(static function ($agent) {
				$agent->setId(7);
				return $agent;
			});

		$this->service->createAgent('Code Agent', 'code_agent', '', '', status: 'Busy until tomorrow');
	}

	public function testCreateAgentRejectsATooLongStatus(): void {
		$this->agentUserService->expects($this->never())->method('createAgentUser');
		$this->agentStatusService->expects($this->never())->method('setAgentStatus');

		$this->expectException(AgentUserException::class);
		$this->service->createAgent('Code Agent', 'code_agent', '', '',
			status: str_repeat('a', AgentStatusService::MAX_STATUS_MESSAGE_LENGTH + 1));
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

	public function testDeleteAgentForUserDeletesTheAgentEntity(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getByUserId')->willReturn($agent);
		$this->agentMapper->expects($this->once())->method('deleteAgent')->with($agent);

		$this->service->deleteAgentForUser('agent1');
	}

	public function testDeleteAgentForUserWithoutAgentIsANoOp(): void {
		$this->agentMapper->method('getByUserId')
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('not an agent'));
		$this->agentMapper->expects($this->never())->method('deleteAgent');

		$this->service->deleteAgentForUser('user1');
	}

	public function testUpdateAgent() {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentStatusService->expects($this->never())->method('setAgentStatus');
		$this->agentMapper->expects($this->once())->method('update')
			->willReturnCallback(static function ($updated) {
				return $updated;
			});

		$updated = $this->service->updateAgent(5, personality: 'New personality');
		$this->assertSame('New personality', $updated->getPersonality());
	}

	public function testUpdateAgentSetsTheStatus(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentStatusService->expects($this->once())
			->method('setAgentStatus')
			->with('agent1', 'New status');
		$this->agentMapper->method('update')
			->willReturnCallback(static function ($updated) {
				return $updated;
			});

		$this->service->updateAgent(5, status: 'New status');
	}

	public function testUpdateAgentRejectsATooLongStatus(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentStatusService->expects($this->never())->method('setAgentStatus');
		$this->agentMapper->expects($this->never())->method('update');

		$this->expectException(AgentUserException::class);
		$this->service->updateAgent(5, status: str_repeat('a', AgentStatusService::MAX_STATUS_MESSAGE_LENGTH + 1));
	}

	public function testUpdateAgentKeepsTheAccessWhenNotProvided(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentMapper->method('update')
			->willReturnCallback(static function ($updated) {
				return $updated;
			});
		$this->agentMapper->expects($this->never())->method('setAcl');
		$this->agentMapper->expects($this->never())->method('getAcl');

		$this->service->updateAgent(5, personality: 'New personality');
	}

	public function testUpdateAgentReplacesTheUsersAccessOnly(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$this->agentMapper->method('getById')->willReturn($agent);
		$this->agentMapper->method('update')
			->willReturnCallback(static function ($updated) {
				return $updated;
			});
		$this->agentMapper->expects($this->once())->method('getAcl')
			->with(5)
			->willReturn([
				['type' => 'user', 'target' => 'user1'],
				['type' => 'group', 'target' => 'group1'],
			]);
		$this->agentMapper->expects($this->once())->method('setAcl')
			->with(5, [
				['type' => 'user', 'target' => 'user2'],
				['type' => 'group', 'target' => 'group1'],
			]);

		$this->service->updateAgent(5, accessUsers: ['user2']);
	}

	public function testCanUserAccessAgentChecksTheUserGroups(): void {
		$agent = $this->createAgentEntity(5, 'agent1');
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['group1']);
		$this->agentMapper->expects($this->once())
			->method('canUserAccess')
			->with(5, 'user1', ['group1'])
			->willReturn(true);

		$this->assertTrue($this->service->canUserAccessAgent($agent, $user));
	}

	public function testGetAgentAccessSplitsUsersAndGroups(): void {
		$this->agentMapper->expects($this->once())
			->method('getAcl')
			->with(5)
			->willReturn([
				['type' => 'user', 'target' => 'user1'],
				['type' => 'group', 'target' => 'group1'],
				['type' => 'user', 'target' => 'user2'],
			]);

		$access = $this->service->getAgentAccess(5);
		$this->assertSame(['user1', 'user2'], $access['users']);
		$this->assertSame(['group1'], $access['groups']);
	}

	private function createAgentEntity(int $id, string $userId): \OCA\Assistant\Db\Agent {
		$agent = new \OCA\Assistant\Db\Agent();
		$agent->setId($id);
		$agent->setUserId($userId);
		$agent->setPersonality('Be nice.');
		return $agent;
	}
}
