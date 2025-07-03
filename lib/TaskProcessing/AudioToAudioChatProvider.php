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
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\ISynchronousProvider;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToSpeech;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AudioToAudioChatProvider implements ISynchronousProvider {

	public function __construct(
		private IL10N $l,
		private TaskProcessingService $taskProcessingService,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-audio2audio:chat';
	}

	public function getName(): string {
		return $this->l->t('Generic Assistant');
	}

	public function getTaskTypeId(): string {
		if (class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat')) {
			return \OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID;
		}
		return AudioToAudioChatTaskType::ID;
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
		return [
			'input_transcript' => new ShapeDescriptor(
				$this->l->t('Input transcript'),
				$this->l->t('Input transcription'),
				EShapeType::Text,
			),
		];
	}

	public function getOptionalOutputShapeEnumValues(): array {
		return [];
	}

	public function process(?string $userId, array $input, callable $reportProgress): array {
		if (!isset($input['input']) || !$input['input'] instanceof File || !$input['input']->isReadable()) {
			throw new RuntimeException('Invalid input file');
		}
		$inputFile = $input['input'];

		if (!isset($input['system_prompt']) || !is_string($input['system_prompt'])) {
			throw new RuntimeException('Invalid system_prompt');
		}
		$systemPrompt = $input['system_prompt'];

		if (!isset($input['history']) || !is_array($input['history'])) {
			throw new RuntimeException('Invalid history');
		}
		/** @var list<string> $history */
		$history = $input['history'];

		//////////////// 3 steps: STT -> LLM -> TTS
		// speech to text
		try {
			$task = new Task(
				AudioToText::ID,
				['input' => $inputFile->getId()],
				Application::APP_ID . ':internal',
				$userId,
			);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$inputTranscription = $taskOutput['output'];
		} catch (Exception $e) {
			$this->logger->warning('Transcription task failed with: ' . $e->getMessage(), ['exception' => $e]);
			throw new RuntimeException('Transcription sub task failed with: ' . $e->getMessage());
		}

		// free prompt
		try {
			$task = new Task(
				TextToTextChat::ID,
				[
					'input' => $inputTranscription,
					'system_prompt' => $systemPrompt,
					'history' => $history,
				],
				Application::APP_ID . ':internal',
				$userId,
			);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$llmResult = $taskOutput['output'];
		} catch (Exception $e) {
			throw new RuntimeException('TextToText sub task failed: ' . $e->getMessage());
		}

		// text to speech
		try {
			$task = new Task(
				TextToSpeech::ID,
				['input' => $llmResult],
				Application::APP_ID . ':internal',
				$userId,
			);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$outputAudioFileId = $taskOutput['speech'];

			return [
				'output' => $this->taskProcessingService->getOutputFileContent($outputAudioFileId),
				'output_transcript' => $llmResult,
				'input_transcript' => $inputTranscription,
			];
		} catch (\Exception $e) {
			$this->logger->warning('Text to speech generation failed with: ' . $e->getMessage(), ['exception' => $e]);
			throw new RuntimeException('Text to speech sub task failed with: ' . $e->getMessage());
		}
	}
}
