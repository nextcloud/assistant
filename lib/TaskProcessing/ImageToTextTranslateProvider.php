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
use OCP\Files\File;
use OCP\IL10N;
use OCP\TaskProcessing\ISynchronousProvider;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\ImageToTextOpticalCharacterRecognition;
use OCP\TaskProcessing\TaskTypes\TextToTextTranslate;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ImageToTextTranslateProvider implements ISynchronousProvider {

	public function __construct(
		private IL10N $l,
		private TaskProcessingService $taskProcessingService,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-image2text:translate';
	}

	public function getName(): string {
		return $this->l->t('Assistant');
	}

	public function getTaskTypeId(): string {
		return ImageToTextTranslateTaskType::ID;
	}

	public function getExpectedRuntime(): int {
		return 60;
	}

	public function getInputShapeEnumValues(): array {
		$translateProvider = $this->taskProcessingService->getPreferredProvider(TextToTextTranslate::ID);

		return [
			'origin_language' => $translateProvider->getInputShapeEnumValues()['origin_language'],
			'target_language' => $translateProvider->getInputShapeEnumValues()['target_language'],
		];
	}

	public function getInputShapeDefaults(): array {
		$translateProvider = $this->taskProcessingService->getPreferredProvider(TextToTextTranslate::ID);
		return [
			'origin_language' => $translateProvider->getInputShapeDefaults()['origin_language'],
		];
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
		if (!isset($input['input']) || !is_array($input['input'])) {
			throw new RuntimeException('Invalid input');
		}
		foreach ($input['input'] as $i => $inputImage) {
			if (!($inputImage instanceof File) || !$inputImage->isReadable()) {
				throw new RuntimeException('Invalid input images');
			}
		}

		if (!isset($input['origin_language']) || !is_string($input['origin_language'])) {
			throw new RuntimeException('Invalid origin_language input');
		}
		if (!isset($input['target_language']) || !is_string($input['target_language'])) {
			throw new RuntimeException('Invalid target_language input');
		}

		$inputCount = count($input['input']);

		// OCR
		$ocrInputs = array_map(static function (File $file) {
			return $file->getId();
		}, $input['input']);
		try {
			$task = new Task(
				ImageToTextOpticalCharacterRecognition::ID,
				['input' => $ocrInputs],
				Application::APP_ID . ':internal',
				$userId,
			);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$ocrOutputs = $taskOutput['output'];
		} catch (Exception $e) {
			$this->logger->warning('OCR sub task failed with: ' . $e->getMessage(), ['exception' => $e]);
			throw new RuntimeException('OCR sub task failed with: ' . $e->getMessage());
		}

		// we did half of the work: all the OCR stuff at once
		$reportProgress(0.5);

		$translationIndex = 0;
		$translatedOutputs = [];
		foreach ($ocrOutputs as $ocrOutput) {
			try {
				$task = new Task(
					TextToTextTranslate::ID,
					[
						'input' => $ocrOutput,
						'origin_language' => $input['origin_language'],
						'target_language' => $input['target_language'],
					],
					Application::APP_ID . ':internal',
					$userId,
				);
				$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
				$translatedOutputs[] = $taskOutput['output'];
				// report progress
				$translationIndex++;
				$translationProgress = $translationIndex / $inputCount;
				$reportProgress(0.5 + ($translationProgress / 2));
			} catch (Exception $e) {
				$this->logger->warning('Translation sub task failed with: ' . $e->getMessage(), ['exception' => $e]);
				throw new RuntimeException('Translation sub task failed with: ' . $e->getMessage());
			}
		}

		// Translation
		return [
			'output' => $translatedOutputs,
		];
	}
}
