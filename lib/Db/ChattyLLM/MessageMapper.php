<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Anupam Kumar <kyteinsky@gmail.com>
 *
 * @author Anupam Kumar <kyteinsky@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Assistant\Db\ChattyLLM;

use OCP\AppFramework\Db\QBMapper;
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
	 * @param integer $messageId
	 * @return Message
	 * @throws \OCP\DB\Exception
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function getMessageById(int $messageId): Message {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Message::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($messageId, IQueryBuilder::PARAM_INT)));

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
	 * @param integer $messageId
	 * @throws \OCP\DB\Exception
	 * @throws \RuntimeException
	 * @return void
	 */
	public function deleteMessageById(int $messageId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($messageId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}
}
