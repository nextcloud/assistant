<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\TaskProcessing;

use Exception;
use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\IL10N;
use OCP\TaskProcessing\ISynchronousProvider;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use Psr\Log\LoggerInterface;
use RuntimeException;

class TextToStickerProvider implements ISynchronousProvider {

	public function __construct(
		private IL10N $l,
		private TaskProcessingService $taskProcessingService,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-text2sticker';
	}

	public function getName(): string {
		return $this->l->t('Assistant fallback');
	}

	public function getTaskTypeId(): string {
		return TextToStickerTaskType::ID;
	}

	public function getExpectedRuntime(): int {
		return 60;
	}

	public function getInputShapeEnumValues(): array {
		return [];
	}

	public function getInputShapeDefaults(): array {
		return [];
	}


	public function getOptionalInputShape(): array {
		return [];
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalInputShapeDefaults(): array {
		return [];
	}

	public function getOutputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalOutputShape(): array {
		return [];
	}

	public function getOptionalOutputShapeEnumValues(): array {
		return [];
	}

	public function process(?string $userId, array $input, callable $reportProgress): array {
		if (!isset($input['input']) || !is_string($input['input'])) {
			throw new RuntimeException('Invalid prompt');
		}
		$input = $input['input'];

		// Generate Image with custom prompt
		try {
			$task = new Task(
				TextToImage::ID,
				[
					'input' => $this->l->t('cartoon, neutral background, sticker of %1$s', [$input]),
					'numberOfImages' => 1
				],
				Application::APP_ID . ':internal',
				$userId,
			);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$images = $taskOutput['images'];
			if (empty($images)) {
				throw new RuntimeException('No images generated');
			}
			$outputImage = $this->taskProcessingService->getOutputFileContent($images[0]);
			return ['image' => $outputImage];
		} catch (Exception $e) {
			$this->logger->warning('Generating image failed with: ' . $e->getMessage(), ['exception' => $e]);
			throw new RuntimeException('Generating image failed with: ' . $e->getMessage());
		}
	}
}
