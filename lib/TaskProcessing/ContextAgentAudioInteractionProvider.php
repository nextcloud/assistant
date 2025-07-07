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
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction;
use OCP\TaskProcessing\TaskTypes\ContextAgentInteraction;
use OCP\TaskProcessing\TaskTypes\TextToSpeech;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ContextAgentAudioInteractionProvider implements ISynchronousProvider {

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
		return $this->l->t('Assistant');
	}

	public function getTaskTypeId(): string {
		return ContextAgentAudioInteraction::ID;
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
		if (!isset($input['input']) || !$input['input'] instanceof File || !$input['input']->isReadable()) {
			throw new RuntimeException('Invalid input file');
		}
		$inputFile = $input['input'];

		if (!isset($input['confirmation']) || !is_numeric($input['confirmation'])) {
			throw new RuntimeException('Invalid confirmation');
		}
		$confirmation = $input['confirmation'];

		if (!isset($input['conversation_token']) || !is_string($input['conversation_token'])) {
			throw new RuntimeException('Invalid conversation_token');
		}
		$conversationToken = $input['conversation_token'];

		//////////////// 3 steps: STT -> Agency -> TTS
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

		// context agent
		try {
			$task = new Task(
				ContextAgentInteraction::ID,
				[
					'input' => $inputTranscription,
					'confirmation' => $confirmation,
					'conversation_token' => $conversationToken,
				],
				Application::APP_ID . ':internal',
				$userId,
			);
			$agencyTaskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
		} catch (Exception $e) {
			throw new RuntimeException('Agency sub task failed: ' . $e->getMessage());
		}

		// the agent might only ask for confirmation
		if ($agencyTaskOutput['output'] !== '') {
			// text to speech
			try {
				$task = new Task(
					TextToSpeech::ID,
					['input' => $agencyTaskOutput['output']],
					Application::APP_ID . ':internal',
					$userId,
				);
				$ttsTaskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
				$outputAudioFileId = $ttsTaskOutput['speech'];
				$outputAudioFileContent = $this->taskProcessingService->getOutputFileContent($outputAudioFileId);
			} catch (\Exception $e) {
				$this->logger->warning('Text to speech generation failed with: ' . $e->getMessage(), ['exception' => $e]);
				throw new RuntimeException('Text to speech sub task failed with: ' . $e->getMessage());
			}
		} else {
			$outputAudioFileContent = '';
		}

		return [
			'output' => $outputAudioFileContent,
			'output_transcript' => $agencyTaskOutput['output'],
			'input_transcript' => $inputTranscription,
			'conversation_token' => $agencyTaskOutput['conversation_token'],
			'actions' => $agencyTaskOutput['actions'],
		];
	}
}
