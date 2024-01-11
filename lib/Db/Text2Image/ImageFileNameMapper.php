<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Db\Text2Image;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ImageFileName>
 */
class ImageFileNameMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'assistant_i_files', ImageFileName::class);
	}

	/**
	 * @param int $generationId
	 * @return ImageFileName[]
	 * @throws Exception
	 */
	public function getImageFileNamesOfGenerationId(int $generationId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('generation_id', $qb->createNamedParameter($generationId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $generationId
	 * @return ImageFileName[]
	 * @throws Exception
	 */
	public function getVisibleImageFileNamesOfGenerationId(int $generationId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('generation_id', $qb->createNamedParameter($generationId, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->eq('hidden', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $fileNameId
	 * @param bool $hidden
	 * @return int
	 */
	public function setFileNameHidden(int $fileNameId, bool $hidden = true): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('hidden', $qb->createNamedParameter($hidden, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($fileNameId, IQueryBuilder::PARAM_INT))
			);

		try {
			return $qb->executeStatement();
		} catch (Exception $e) {
			return 0;
		}
	}


	/**
	 * @param int $generationId
	 * @param int $fileNameId
	 * @return ImageFileName|Entity|null
	 */
	public function getImageFileNameOfGenerationId(int $generationId, int $fileNameId): ImageFileName|Entity|null {
		$qb = $this->db->getQueryBuilder();

		$qb->select('file_name')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('generation_id', $qb->createNamedParameter($generationId, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($fileNameId, IQueryBuilder::PARAM_INT))
			);

		try {
			return $this->findEntity($qb);
		} catch (MultipleObjectsReturnedException|DoesNotExistException|Exception $e) {
			return null;
		}
	}

	/**
	 * @param int $generationId
	 * @param string $fileName
	 * @return ImageFileName|Entity
	 * @throws Exception
	 */
	public function createImageFileName(int $generationId, string $fileName): ImageFileName|Entity {
		$imageFile = new ImageFileName();
		$imageFile->setGenerationId($generationId);
		$imageFile->setFileName($fileName);
		return $this->insert($imageFile);
	}

	/**
	 * @param int $generationId
	 * @return void
	 * @throws Exception
	 */
	public function deleteImageFileNamesOfGenerationId(int $generationId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('generation_id', $qb->createNamedParameter($generationId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}

	/**
	 * @param int $id
	 * @return void
	 * @throws Exception
	 */
	public function deleteImageFileName(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}
}
