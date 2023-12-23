<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\TPAssistant\Db\FreePrompt;

use DateTime;
use OCA\TPAssistant\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
/**
 * @implements QBMapper<Prompt>
 */
class PromptMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'assistant_t_prompts', Prompt::class);
	}

	/**
	 * @param int $id
	 * @return Prompt
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getPrompt(int $id): Prompt {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		/** @var Prompt $retVal */
		$retVal = $this->findEntity($qb);
		return $retVal;
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @return Prompt
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getPromptOfUser(int $id, string $userId): Prompt {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		/** @var Prompt $retVal */
		$retVal = $this->findEntity($qb);
		return $retVal;
	}

	/**
	 * @param string $userId
	 * @param string $value
	 * @return Prompt
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getPromptOfUserByValue(string $userId, string $value): Prompt {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('value', $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR))
			);
		/** @var Prompt $retVal */
		$retVal = $this->findEntity($qb);
		return $retVal;
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws Exception
	 */
	public function getPromptsOfUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		$qb->orderBy('timestamp', 'DESC')
			->setMaxResults(Application::MAX_STORED_TEXT_PROMPTS_PER_USER);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param string $value
	 * @param int|null $timestamp
	 * @return Prompt
	 * @throws Exception
	 */
	public function createPrompt(string $userId, string $value, ?int $timestamp = null): Prompt {
		if ($timestamp === null) {
			$timestamp = (new DateTime())->getTimestamp();
		}
		
		try {
			$prompt = $this->getPromptOfUserByValue($userId, $value);
			$prompt->setTimestamp($timestamp);
			/** @var Prompt $updatedPrompt */
			$updatedPrompt = $this->update($prompt);
			return $updatedPrompt;
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
		}

		// If the prompt does not exist, cleanup and create it
		$prompt = new Prompt();
		$prompt->setUserId($userId);
		$prompt->setValue($value);
		$prompt->setTimestamp($timestamp);
		/** @var Prompt $insertedPrompt */
		$insertedPrompt = $this->insert($prompt);

		$this->cleanupUserPrompts($userId);

		return $insertedPrompt;
	}

	/**
	 * @param string $userId
	 * @return void
	 * @throws Exception
	 */
	public function deleteUserPrompts(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}

	/**
	 * @param string $userId
	 * @return void
	 * @throws Exception
	 */
	public function cleanupUserPrompts(string $userId): void {
		$qb = $this->db->getQueryBuilder();

		// get the last N prompts in descending order
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->orderBy('timestamp', 'DESC')
			->setMaxResults(Application::MAX_STORED_TEXT_PROMPTS_PER_USER);

		$req = $qb->executeQuery();

		$lastPromptTs = [];
		/** @var mixed[] $row */
		while ($row = $req->fetch()) {			
			$lastPromptTs[] = (int)$row['timestamp'];
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		// if we have at least 20 prompts stored, delete everything but the last 20 ones
		if (count($lastPromptTs) === Application::MAX_STORED_TEXT_PROMPTS_PER_USER) {
			$firstPromptTsToKeep = end($lastPromptTs);
			$qb->delete($this->getTableName())
				->where(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->lt('timestamp', $qb->createNamedParameter($firstPromptTsToKeep, IQueryBuilder::PARAM_INT))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();
		}
	}
}
