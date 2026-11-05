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
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Management of the "agent as a user" entities for the admin settings
 */
class AgentsService {

	private const MAX_PERSONALITY_LENGTH = 64_000;
	private const MAX_SCOPE_LENGTH = 4_000;

	public function __construct(
		private AgentMapper $agentMapper,
		private AgentUserService $agentUserService,
		private IUserManager $userManager,
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
	 * @throws AgentUserException
	 * @throws Exception
	 */
	public function createAgent(string $name, string $userId, string $personality, string $scope): Agent {
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
		return $this->agentMapper->insert($agent);
	}

	/**
	 * @throws AgentUserException when the agent user does not exist anymore
	 * @throws Exception
	 */
	public function updateAgent(int $id, ?string $personality = null, ?string $scope = null): Agent {
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
		return $this->agentMapper->update($agent);
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
}
