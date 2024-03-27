<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Service\Text2Image;

use Exception;
use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\Text2Image\ImageGenerationMapper;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CleanUpService {
	public function __construct(
		private LoggerInterface $logger,
		private ImageGenerationMapper $imageGenerationMapper,
		private Text2ImageHelperService $text2ImageHelperService,
		private IConfig $config
	) {
	}

	/**
	 * @param int|null $maxAge
	 * @return array{deleted_files: int, file_deletion_errors: int, deleted_generations: int}
	 * @throws Exception
	 */
	public function cleanupGenerationsAndFiles(?int $maxAge = null): array {
		if ($maxAge === null) {
			$maxAge = intval($this->config->getUserValue(
				Application::APP_ID,
				'max_image_generation_idle_time',
				strval(Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME)
			) ?: Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME);
		}
		$cleanedUp = $this->imageGenerationMapper->cleanupImageGenerations(intval($maxAge));

		if (intval($cleanedUp['deleted_generations'] === 0)) {
			$this->logger->debug('No idle generations to delete');
			throw new Exception('No idle generations to delete');
		}

		try {
			$imageDataFolder = $this->text2ImageHelperService->getImageDataFolder();
		} catch (NotFoundException | RuntimeException $e) {
			$this->logger->debug('Image data folder could not be accessed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			throw new Exception('Image data folder could not be accessed');
		}

		$deletedFiles = 0;
		$deletionErrors = 0;

		foreach ($cleanedUp['file_names'] as $fileName) {
			try {
				$imageDataFolder->getFile($fileName)->delete();
				$deletedFiles++;
			} catch (NotPermittedException $e) {
				$this->logger->debug('Image file could not be deleted: ' . $e->getMessage(), ['app' => Application::APP_ID]);
				$deletionErrors++;
			}
		}

		$this->logger->debug('Deleted ' . $deletedFiles . ' files associated with ' . $cleanedUp['deleted_generations'] .
			' idle generations. Failed to delete ' . $deletionErrors . ' files.');

		return ['deleted_files' => $deletedFiles, 'file_deletion_errors' => $deletionErrors, 'deleted_generations' => $cleanedUp['deleted_generations']];
	}
}
