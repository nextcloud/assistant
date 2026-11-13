<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\Db\Agent;
use OCA\Assistant\Db\AgentMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Management of the "agent as a user" entities for the admin settings
 */
class AgentsService {

	public const ACL_TYPE_USER = 'user';
	public const ACL_TYPE_GROUP = 'group';

	private const MAX_PERSONALITY_LENGTH = 64_000;
	private const MAX_SCOPE_LENGTH = 4_000;

	public function __construct(
		private AgentMapper $agentMapper,
		private AgentUserService $agentUserService,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return list<Agent>
	 * @throws Exception
	 */
	public function getAllAgents(): array {
		return $this->agentMapper->getAllAgents();
	}

	/**
	 * @param int $id
	 * @return Agent|null
	 */
	public function getAgentById(int $id): ?Agent {
		try {
			return $this->agentMapper->getById($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return null;
		}
	}

	/**
	 * @param string $userId
	 * @return Agent|null
	 */
	public function getAgentByUserId(string $userId): ?Agent {
		try {
			return $this->agentMapper->getByUserId($userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return null;
		}
	}

	/**
	 * @param string|null $name
	 * @return array<string, Agent>
	 * @throws Exception
	 */
	public function getAgentsByUserIds(array $userIds): array {
		return $this->agentMapper->getByUserIds(array_values(array_filter($userIds)));
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @param string $personality
	 * @param string $scope
	 * @param list<string> $accessUsers User ids that may access the agent
	 * @param list<string> $accessGroups Group ids that may access the agent
	 * @return Agent
	 * @throws AgentUserException
	 * @throws Exception
	 */
	public function createAgent(
		string $name, string $userId, string $personality, string $scope,
		array $accessUsers = [], array $accessGroups = [],
	): Agent {
		$agent = new Agent();
		$agent->setUserId(trim(mb_strtolower($userId)));
		$agent->setPersonality(trim($personality));
		$agent->setScope(trim($scope));
		try {
			$this->agentUserService->createAgentUser($name, $userId);
		} catch (AgentUserException $e) {
			$this->logger->warning('Failed to provision the agent user', ['exception' => $e]);
			throw $e;
		}
		$agent = $this->agentMapper->insert($agent);
		if ($accessUsers !== [] || $accessGroups !== []) {
			$this->agentMapper->setAcl((int)$agent->getId(), $this->makeAclEntries($accessUsers, $accessGroups));
		}
		return $agent;
	}

	/**
	 * @param int $id
	 * @param string|null $personality
	 * @param string|null $scope
	 * @param list<string>|null $accessUsers When not null, replaces the list of user ids that may access the agent
	 * @param list<string>|null $accessGroups When not null, replaces the list of group ids that may access the agent
	 * @return Agent
	 * @throws AgentUserException when the agent does not exist anymore
	 * @throws Exception
	 */
	public function updateAgent(
		int $id, ?string $personality = null, ?string $scope = null,
		?array $accessUsers = null, ?array $accessGroups = null,
	): Agent {
		try {
			$agent = $this->agentMapper->getById($id);
		} catch (DoesNotExistException $e) {
			throw new AgentUserException('Agent not found');
		}
		if ($personality !== null) {
			if (strlen($personality) > self::MAX_PERSONALITY_LENGTH) {
				throw new AgentUserException('The agent personality is too long');
			}
			$agent->setPersonality(trim($personality));
		}
		if ($scope !== null) {
			if (strlen($scope) > self::MAX_SCOPE_LENGTH) {
				throw new AgentUserException('The agent scope is too long');
			}
			$agent->setScope(trim($scope));
		}
		$agent = $this->agentMapper->update($agent);
		if ($accessUsers !== null || $accessGroups !== null) {
			$entries = $this->getAgentAccess($id);
			$accessUsers ??= $entries['users'];
			$accessGroups ??= $entries['groups'];
			$this->agentMapper->setAcl($id, $this->makeAclEntries($accessUsers, $accessGroups));
		}
		return $agent;
	}

	/**
	 * @param int $id
	 * @param bool $deleteUser Whether the related NC user should be deleted too
	 * @return void
	 * @throws Exception
	 */
	public function deleteAgent(int $id, bool $deleteUser = false): void {
		try {
			$agent = $this->agentMapper->getById($id);
		} catch (DoesNotExistException $e) {
			return;
		}
		$this->agentMapper->deleteAgent($agent);

		if ($deleteUser) {
			$user = $this->userManager->get($agent->getUserId());
			if ($user !== null) {
				$user->delete();
			}
		}
	}

	/**
	 * The user ids and group ids that may access the agent.
	 * Both lists are empty when the access is not restricted.
	 *
	 * @param int $agentId
	 * @return array{users: list<string>, groups: list<string>}
	 * @throws Exception
	 */
	public function getAgentAccess(int $agentId): array {
		$users = [];
		$groups = [];
		foreach ($this->agentMapper->getAcl($agentId) as $entry) {
			if ($entry['type'] === self::ACL_TYPE_USER) {
				$users[] = $entry['target'];
			} elseif ($entry['type'] === self::ACL_TYPE_GROUP) {
				$groups[] = $entry['target'];
			}
		}
		return ['users' => $users, 'groups' => $groups];
	}

	/**
	 * Whether the user may access the agent:
	 * an empty access list means every user has access,
	 * otherwise the user must be listed by id or belong to a listed group.
	 *
	 * @param Agent $agent
	 * @param IUser $user
	 * @return bool
	 * @throws Exception
	 */
	public function canUserAccessAgent(Agent $agent, IUser $user): bool {
		return $this->agentMapper->canUserAccess(
			(int)$agent->getId(),
			$user->getUID(),
			$this->groupManager->getUserGroupIds($user),
		);
	}

	/**
	 * @param list<string> $accessUsers
	 * @param list<string> $accessGroups
	 * @return list<array{type: string, target: string}>
	 */
	private function makeAclEntries(array $accessUsers, array $accessGroups): array {
		$entries = [];
		foreach ($accessUsers as $userId) {
			$entries[] = ['type' => self::ACL_TYPE_USER, 'target' => $userId];
		}
		foreach ($accessGroups as $groupId) {
			$entries[] = ['type' => self::ACL_TYPE_GROUP, 'target' => $groupId];
		}
		return $entries;
	}
}
