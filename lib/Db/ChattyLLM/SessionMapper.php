<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db\ChattyLLM;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Session>
 */
class SessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'assistant_chat_sns', Session::class);
	}

	/**
	 * @param string $userId
	 * @param integer $sessionId
	 * @return boolean
	 * @throws \OCP\DB\Exception
	 */
	public function exists(string $userId, int $sessionId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb) !== null;
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			return false;
		} catch (\OCP\AppFramework\Db\MultipleObjectsReturnedException $e) {
			return true;
		}
	}

	/**
	 * @param string $userId
	 * @param int $sessionId
	 * @return Session
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getUserSession(string $userId, int $sessionId): Session {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Session::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getUserSessions(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Session::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('timestamp', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param integer $sessionId
	 * @param string $title
	 * @throws \OCP\DB\Exception
	 * @throws \RuntimeException
	 */
	public function updateSessionTitle(string $userId, int $sessionId, string $title) {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('title', $qb->createPositionalParameter($title, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)));

		$qb->executeStatement();
	}

	/**
	 * @param string $userId
	 * @param integer $sessionId
	 * @throws \OCP\DB\Exception
	 * @throws \RuntimeException
	 */
	public function deleteSession(string $userId, int $sessionId) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)));

		$qb->executeStatement();
	}
}
