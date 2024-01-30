<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Db\Text2Image;

use DateTime;
use OCA\TpAssistant\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ImageGeneration>
 */
class ImageGenerationMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private ImageFileNameMapper $imageFileNameMapper,
		private StaleGenerationMapper $staleGenerationMapper
	) {
		parent::__construct($db, 'assistant_i_gens', ImageGeneration::class);
	}

	/**
	 * @param string $imageGenId
	 * @return ImageGeneration|Entity
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getImageGenerationOfImageGenId(string $imageGenId): ImageGeneration|Entity {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('image_gen_id', $qb->createNamedParameter($imageGenId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $imageGenId
	 * @param string $prompt
	 * @param string $userId
	 * @param int|null $expCompletionTime
	 * @return ImageGeneration|Entity
	 * @throws Exception
	 */
	public function createImageGeneration(string $imageGenId, string $prompt = '', string $userId = '', ?int $expCompletionTime = null): ImageGeneration|Entity {
		$imageGeneration = new ImageGeneration();
		$imageGeneration->setImageGenId($imageGenId);
		$imageGeneration->setTimestamp((new DateTime())->getTimestamp());
		$imageGeneration->setPrompt($prompt);
		$imageGeneration->setUserId($userId);
		$imageGeneration->setIsGenerated(false);
		$imageGeneration->setFailed(false);
		$imageGeneration->setNotifyReady(false);
		$imageGeneration->setExpGenTime($expCompletionTime ?? (new DateTime())->getTimestamp());
		return $this->insert($imageGeneration);
	}

	/**
	 * Set image as processed
	 * @param string $imageGenId
	 * @param bool $isGenerated
	 * @return int
	 * @throws Exception
	 */
	public function setImagesGenerated(string $imageGenId, bool $isGenerated = true): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_generated', $qb->createNamedParameter($isGenerated, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq('image_gen_id', $qb->createNamedParameter($imageGenId, IQueryBuilder::PARAM_STR))
			);
		$count = $qb->executeStatement();
		$qb->resetQueryParts();
		return $count;
	}

	/**
	 * Set failed flag
	 * @param string $imageGenId
	 * @param bool $isFailed
	 * @return int
	 * @throws Exception
	 */
	public function setFailed(string $imageGenId, bool $isFailed = true): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('failed', $qb->createNamedParameter($isFailed, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq('image_gen_id', $qb->createNamedParameter($imageGenId, IQueryBuilder::PARAM_STR))
			);
		$count = $qb->executeStatement();
		$qb->resetQueryParts();
		return $count;
	}

	/**
	 * Touch timestamp of image generation
	 * @param string $imageGenId
	 * @return int
	 * @throws Exception
	 */
	public function touchImageGeneration(string $imageGenId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('timestamp', $qb->createNamedParameter((new DateTime())->getTimestamp(), IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('image_gen_id', $qb->createNamedParameter($imageGenId, IQueryBuilder::PARAM_STR))
			);
		$count = $qb->executeStatement();
		$qb->resetQueryParts();
		return $count;
	}

	/**
	 * Delete image generation and associated file name entries
	 * @param string $imageGenId
	 * @return void
	 * @throws Exception
	 */
	public function deleteImageGeneration(string $imageGenId): void {
		// Also delete associated file names, so first get the id for imageGenId:
		try {
			$rowId = $this->getImageGenerationOfImageGenId($imageGenId)->getId();
		} catch (Exception|DoesNotExistException|MultipleObjectsReturnedException $e) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('image_gen_id', $qb->createNamedParameter($imageGenId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();

		// If the  previous query was successful, delete associated file names:
		$this->imageFileNameMapper->deleteImageFileNamesOfGenerationId($rowId);
	}

	/**
	 * Set notifyReady flag
	 * @param string $imageGenId
	 * @param bool $notifyReady
	 * @return int
	 * @throws Exception
	 */
	public function setNotifyReady(string $imageGenId, bool $notifyReady): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('notify_ready', $qb->createNamedParameter($notifyReady, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq('image_gen_id', $qb->createNamedParameter($imageGenId, IQueryBuilder::PARAM_STR))
			);
		$count = $qb->executeStatement();
		$qb->resetQueryParts();
		return $count;
	}

	/**
	 * @param int $maxAge
	 * @return array{deleted_generations: int, file_names: array<string>}
	 * @throws Exception
	 * @throws \RuntimeException
	 */
	public function cleanupImageGenerations(int $maxAge = Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME): array {
		$ts = (new DateTime())->getTimestamp();
		$maxTimestamp = $ts - $maxAge;

		$qb = $this->db->getQueryBuilder();

		// get generations that will be deleted
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter($maxTimestamp, IQueryBuilder::PARAM_INT))
			);

		$generations = $this->findEntities($qb);
		$qb->resetQueryParts();

		$fileNames = [];
		$imageGenIds = [];
		$generationIds = [];

		foreach ($generations as $generation) {
			$generationFiles = $this->imageFileNameMapper->getImageFileNamesOfGenerationId($generation->getId());
			array_map(function ($generationFile) use (&$fileNames) {
				$fileNames[] = $generationFile->getFileName();
			}, $generationFiles);
			$imageGenIds[] = $generation->getImageGenId();
			$generationIds[] = $generation->getId();
		}

		// Only now delete associated file names if we encountered no errors:
		/** @var int $genId */
		foreach ($generationIds as $genId) {
			$this->imageFileNameMapper->deleteImageFileNamesOfGenerationId($genId);
		}

		// Add the image generation ids to the stale generations table:
		/** @var string $imageGenId */
		foreach ($imageGenIds as $imageGenId) {
			$this->staleGenerationMapper->createStaleGeneration($imageGenId);
		}

		// Delete generations
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter($maxTimestamp, IQueryBuilder::PARAM_INT))
			);

		$countDelGens = $qb->executeStatement();

		return [
			'deleted_generations' => $countDelGens,
			'file_names' => $fileNames,
		];
	}
}
