<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

use OCA\Assistant\Db\Assignment;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Assignment>
 */
class AssignmentMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private ITimeFactory $timeFactory,
	) {
		parent::__construct($db, 'assistant_assignments', Assignment::class);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @return Assignment
	 */
	public function find(string $userId, int $assignmentId): Assignment {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($assignmentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	/**
	 * @return boolean
	 * @throws \OCP\DB\Exception
	 */
	public function exists(string $userId, int $assignmentId): bool {
		try {
			return $this->find($userId, $assignmentId) !== null;
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			return false;
		} catch (\OCP\AppFramework\Db\MultipleObjectsReturnedException $e) {
			return true;
		}
	}

	/**
	 * @return \Generator<void, int, Assignment>
	 * @throws \OCP\DB\Exception
	 */
	public function findForUser(string $userId): \Generator {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Assignment::$columns)
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'DESC');

		yield * $this->yieldEntities($qb);
	}

	/**
	 * @return \Generator<void, int, Assignment>
	 * @throws \OCP\DB\Exception
	 */
	public function findDueAssignmentsForUser(string $userId): \Generator {
		foreach ($this->findForUser($userId) as $assignment) {
			if (!$assignment->isDueToRun($this->timeFactory->now())) {
				continue;
			}
			yield $assignment;
		}
	}
}
