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
 * @extends QBMapper<Message>
 */
class MessageMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'assistant_chat_msgs', Message::class);
	}

	/**
	 * @param integer $sessionId
	 * @param integer $n
	 * @return Message
	 * @throws \OCP\DB\Exception
	 * @throws \RuntimeException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function getFirstNMessages(int $sessionId, int $n = 1): Message {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Message::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->setMaxResults($n);

		return $this->findEntity($qb);
	}

	/**
	 * @param integer $sessionId
	 * @return Message
	 * @throws \OCP\DB\Exception
	 * @throws \RuntimeException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function getLastHumanMessage(int $sessionId): Message {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Message::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('role', $qb->createPositionalParameter('human', IQueryBuilder::PARAM_STR)))
			->orderBy('timestamp', 'DESC')
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function getLastNonEmptyHumanMessage(int $sessionId): Message {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Message::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('role', $qb->createPositionalParameter('human', IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->nonEmptyString('content'))
			->orderBy('timestamp', 'DESC')
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $sessionId
	 * @param int $cursor
	 * @param int $limit
	 * @return array<Message>
	 * @throws \OCP\DB\Exception
	 */
	public function getMessages(int $sessionId, int $cursor, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Message::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'DESC')
			->setFirstResult($cursor);

		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}

		$messages = $this->findEntities($qb);
		return array_reverse($messages);
	}

	/**
	 * @param int $sessionId
	 * @param integer $messageId
	 * @return Message
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getMessageById(int $sessionId, int $messageId): Message {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Message::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($messageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $sessionId
	 * @param int $ocpTaskId
	 * @return Message
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function getMessageByTaskId(int $sessionId, int $ocpTaskId): Message {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Message::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('ocp_task_id', $qb->createPositionalParameter($ocpTaskId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $sessionId
	 * @throws \OCP\DB\Exception
	 * @throws \RuntimeException
	 * @return void
	 */
	public function deleteMessagesBySession(int $sessionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}

	/**
	 * @param int $sessionId
	 * @param integer $messageId
	 * @return void
	 * @throws Exception
	 */
	public function deleteMessageById(int $sessionId, int $messageId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($messageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('session_id', $qb->createPositionalParameter($sessionId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}
}
