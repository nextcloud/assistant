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
		return $agent;
	}
}
