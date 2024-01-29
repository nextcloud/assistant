<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\TpAssistant\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Task>
 */
class TaskMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'assistant_text_tasks', Task::class);
	}

	/**
	 * @param int $id
	 * @param int $taskType
	 * @return Task
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTask(int $id): Task {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		/** @var Task $retVal */
		$retVal = $this->findEntity($qb);
		return $retVal;
	}

	/**
	 * @param int $id
	 * @param int $category
	 * @return Task
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTaskByOcpTaskIdAndCategory(int $ocpTaskId, int $category): Task {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('ocp_task_id', $qb->createNamedParameter($ocpTaskId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('category', $qb->createNamedParameter($category, IQueryBuilder::PARAM_INT))
			);

		/** @var Task $retVal */
		$retVal = $this->findEntity($qb);
		return $retVal;
	}

	/**
	 * @param int $id
	 * @param int $category
	 * @return array<Task>
	 * @throws Exception
	 */
	public function getTasksByOcpTaskIdAndCategory(int $ocpTaskId, int $category): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('ocp_task_id', $qb->createNamedParameter($ocpTaskId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('category', $qb->createNamedParameter($category, IQueryBuilder::PARAM_INT))
			);

		/** @var array<Task> $retVal */
		$retVal = $this->findEntities($qb);
		return $retVal;
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @return Task
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getTaskOfUser(int $id, string $userId): Task {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		/** @var Task $retVal */
		$retVal = $this->findEntity($qb);
		return $retVal;
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws Exception
	 */
	public function getTasksOfUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param array<mixed> $inputs
	 * @param string|null $output
	 * @param int|null $timestamp
	 * @param int|null $ocpTaskId
	 * @param string|null $taskType
	 * @param string|null $appId
	 * @param int $status
	 * @param int $category
	 * @param string $identifier
	 * @return Task
	 * @throws Exception
	 */
	public function createTask(
		string $userId,
		array $inputs,
		?string $output,
		?int $timestamp = null,
		?int $ocpTaskId = null,
		?string $taskType = null,
		?string $appId = null,
		int $status = 0,
		int $category = 0,
		string $identifier = ''): Task {
		if ($timestamp === null) {
			$timestamp = (new DateTime())->getTimestamp();
		}

		$task = new Task();
		$task->setUserId($userId);
		$task->setInputs(json_encode($inputs));
		$task->setTimestamp($timestamp);
		$task->setOutput($output);
		$task->setOcpTaskId($ocpTaskId);
		$task->setTaskType($taskType);
		$task->setAppId($appId);
		$task->setStatus($status);
		$task->setCategory($category);
		$task->setIndentifer($identifier);
		/** @var Task $insertedTask */
		$insertedTask = $this->insert($task);

		return $insertedTask;
	}

	/**
	 * @param string $userId
	 * @return void
	 * @throws Exception
	 */
	public function deleteUserTasks(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}

	/**
	 * Clean up tasks older than 14 days
	 * @return int number of deleted rows
	 * @throws Exception
	 * @throws \RuntimeException
	 */
	public function cleanupOldTasks(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter(time() - 14 * 24 * 60 * 60, IQueryBuilder::PARAM_INT))
			);
		return $qb->executeStatement();
	}
}
