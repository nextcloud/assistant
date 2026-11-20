<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Agent>
 */
class AgentMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'assistant_agents', Agent::class);
	}

	/**
	 * @param int $id
	 * @return Agent
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getById(int $id): Agent {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Agent::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<Agent>
	 * @throws Exception
	 */
	public function getAllAgents(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Agent::$columns)
			->from($this->getTableName())
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @return Agent
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByUserId(string $userId): Agent {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Agent::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}

	/**
	 * Whether a list of user ids contains registered agents, keyed by user id
	 *
	 * @param list<string> $userIds
	 * @return array<string, Agent>
	 * @throws Exception
	 */
	public function getByUserIds(array $userIds): array {
		if ($userIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select(Agent::$columns)
			->from($this->getTableName())
			->where($qb->expr()->in('user_id', $qb->createParameter('userIds')))
			->setParameter('userIds', $userIds, IQueryBuilder::PARAM_STR_ARRAY);
		$result = $qb->executeQuery();
		$agents = [];
		while (($row = $result->fetchAssociative()) !== false) {
			$agent = $this->mapRowToEntity($row);
			$agents[$agent->getUserId()] = $agent;
		}
		$result->closeCursor();
		return $agents;
	}

	/**
	 * @param Agent $agent
	 * @return Agent
	 * @throws Exception
	 */
	public function deleteAgent(Agent $agent): Agent {
		$this->delete($agent);

		$qb = $this->db->getQueryBuilder();
		$qb->delete('assistant_agents_acl')
			->where($qb->expr()->eq('agent_id', $qb->createPositionalParameter($agent->getId(), IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		return $agent;
	}

	/**
	 * The access control entries of an agent, as a list of {type, target}
	 *
	 * @param int $agentId
	 * @return list<array{type: string, target: string}>
	 * @throws Exception
	 */
	public function getAcl(int $agentId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('type', 'target')
			->from('assistant_agents_acl')
			->where($qb->expr()->eq('agent_id', $qb->createPositionalParameter($agentId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();

		$entries = [];
		while (($row = $result->fetchAssociative()) !== false) {
			$entries[] = [
				'type' => (string)$row['type'],
				'target' => (string)$row['target'],
			];
		}
		$result->closeCursor();
		return $entries;
	}

	/**
	 * Replace the access control entries of an agent
	 *
	 * @param int $agentId
	 * @param list<array{type: string, target: string}> $entries
	 * @return void
	 * @throws Exception
	 */
	public function setAcl(int $agentId, array $entries): void {
		$this->db->beginTransaction();
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('assistant_agents_acl')
				->where($qb->expr()->eq('agent_id', $qb->createPositionalParameter($agentId, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

			foreach ($entries as $entry) {
				$qb = $this->db->getQueryBuilder();
				$qb->insert('assistant_agents_acl')
					->values([
						'agent_id' => $qb->createPositionalParameter($agentId, IQueryBuilder::PARAM_INT),
						'type' => $qb->createPositionalParameter($entry['type'], IQueryBuilder::PARAM_STR),
						'target' => $qb->createPositionalParameter($entry['target'], IQueryBuilder::PARAM_STR),
					]);
				$qb->executeStatement();
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw new Exception('Failed to store the agent access list: ' . $e->getMessage(), $e instanceof Exception ? $e->getCode() : 0, $e);
		}
	}

	/**
	 * Whether the user (by own id or group membership) matches an entry of the agent ACL.
	 * An empty ACL means every user has access.
	 *
	 * @param int $agentId
	 * @param string $userId
	 * @param list<string> $groupIds
	 * @return bool
	 * @throws Exception
	 */
	public function canUserAccess(int $agentId, string $userId, array $groupIds): bool {
		$qb = $this->db->getQueryBuilder();
		$accessConditions = $qb->expr()->orX();
		$accessConditions->add($qb->expr()->andX(
			$qb->expr()->eq('type', $qb->createNamedParameter('user', IQueryBuilder::PARAM_STR)),
			$qb->expr()->eq('target', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
		));
		if ($groupIds !== []) {
			$accessConditions->add($qb->expr()->andX(
				$qb->expr()->eq('type', $qb->createNamedParameter('group', IQueryBuilder::PARAM_STR)),
				$qb->expr()->in('target', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY)),
			));
		}

		$qb->select('id')
			->from('assistant_agents_acl')
			->where($qb->expr()->eq('agent_id', $qb->createNamedParameter($agentId, IQueryBuilder::PARAM_INT)))
			->andWhere($accessConditions)
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$hasAccess = $result->fetchOne() !== false;
		$result->closeCursor();
		if ($hasAccess) {
			return true;
		}

		// an agent with an empty access list is accessible to every user
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('assistant_agents_acl')
			->where($qb->expr()->eq('agent_id', $qb->createNamedParameter($agentId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$hasRestrictions = $result->fetchOne() !== false;
		$result->closeCursor();
		return !$hasRestrictions;
	}
}
