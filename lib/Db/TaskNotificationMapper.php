<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<TaskNotification>
 */
class TaskNotificationMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'assistant_task_notif', TaskNotification::class);
	}

	/**
	 * @param int $ocpTaskId
	 * @return ?TaskNotification
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByTaskId(int $ocpTaskId): ?TaskNotification {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('ocp_task_id', $qb->createNamedParameter($ocpTaskId, IQueryBuilder::PARAM_INT))
			);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * @param int $ocpTaskId
	 * @return void
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteByTaskId(int $ocpTaskId): void {
		$existingEntry = $this->getByTaskId($ocpTaskId);
		if ($existingEntry !== null) {
			$this->delete($existingEntry);
		}
	}

	/**
	 * @param int $ocpTaskId
	 * @return TaskNotification|null
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function createTaskNotification(int $ocpTaskId): ?TaskNotification {
		$existingEntry = $this->getByTaskId($ocpTaskId);
		if ($existingEntry !== null) {
			return $existingEntry;
		}

		$nowTimestamp = (new DateTime())->getTimestamp();
		$newEntry = new TaskNotification();
		$newEntry->setOcpTaskId($ocpTaskId);
		$newEntry->setTimestamp($nowTimestamp);
		$this->insert($newEntry);

		return $this->getByTaskId($ocpTaskId);
	}
}
