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
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\Exception\ProcessingException;
use OCP\TaskProcessing\IProvider;
use OCP\TaskProcessing\ISynchronousOptionsAwareProvider;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\SynchronousProviderOptions;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\AudioToAudioTranslate;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToSpeech;
use OCP\TaskProcessing\TaskTypes\TextToTextTranslate;
use Psr\Log\LoggerInterface;

class AudioToAudioTranslateProvider implements IProvider, ISynchronousOptionsAwareProvider {

	public function __construct(
		private IL10N $l,
		private TaskProcessingService $taskProcessingService,
		private LoggerInterface $logger,
		private IFactory $l10nFactory,
		private IUserManager $userManager,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-audio2audio:translate';
	}

	public function getName(): string {
		return $this->l->t('Assistant');
	}

	public function getTaskTypeId(): string {
		return AudioToAudioTranslate::ID;
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
		$translateProvider = $this->taskProcessingService->getPreferredProvider(TextToTextTranslate::ID);
		$translateOptionalInputShape = $translateProvider->getOptionalInputShape();
		$ttsProvider = $this->taskProcessingService->getPreferredProvider(TextToSpeech::ID);
		$ttsOptionalInputShape = $ttsProvider->getOptionalInputShape();

		$optionalInputShape = [];

		if (isset($translateOptionalInputShape['model'])) {
			$optionalInputShape['translation_model'] = new ShapeDescriptor(
				$this->l->t('Translation model'),
				$this->l->t('The model used to translate'),
				EShapeType::Enum,
			);
		}

		if (isset($ttsOptionalInputShape['model'])) {
			$optionalInputShape['speech_model'] = new ShapeDescriptor(
				$ttsOptionalInputShape['model']->getName(),
				$ttsOptionalInputShape['model']->getDescription(),
				EShapeType::Enum,
			);
		}

		if (isset($ttsOptionalInputShape['voice'])) {
			$optionalInputShape['speech_voice'] = new ShapeDescriptor(
				$ttsOptionalInputShape['voice']->getName(),
				$ttsOptionalInputShape['voice']->getDescription(),
				EShapeType::Enum,
			);
		}

		if (isset($ttsOptionalInputShape['speed'])) {
			$optionalInputShape['speech_speed'] = new ShapeDescriptor(
				$ttsOptionalInputShape['speed']->getName(),
				$ttsOptionalInputShape['speed']->getDescription(),
				EShapeType::Number,
			);
		}

		return $optionalInputShape;
	}

	public function getOptionalInputShapeEnumValues(): array {
		$translateProvider = $this->taskProcessingService->getPreferredProvider(TextToTextTranslate::ID);
		$translateOptionalInputShape = $translateProvider->getOptionalInputShape();
		$ttsProvider = $this->taskProcessingService->getPreferredProvider(TextToSpeech::ID);
		$ttsOptionalInputShape = $ttsProvider->getOptionalInputShape();

		$optionalInputShapeEnumValues = [];

		if (isset($translateOptionalInputShape['model'])) {
			$optionalInputShapeEnumValues['translation_model'] = $translateProvider->getOptionalInputShapeEnumValues()['model'];
		}

		if (isset($ttsOptionalInputShape['model'])) {
			$optionalInputShapeEnumValues['speech_model'] = $ttsProvider->getOptionalInputShapeEnumValues()['model'];
		}

		if (isset($ttsOptionalInputShape['voice'])) {
			$optionalInputShapeEnumValues['speech_voice'] = $ttsProvider->getOptionalInputShapeEnumValues()['voice'];
		}

		return $optionalInputShapeEnumValues;
	}

	public function getOptionalInputShapeDefaults(): array {
		$translateProvider = $this->taskProcessingService->getPreferredProvider(TextToTextTranslate::ID);
		$translateOptionalInputShape = $translateProvider->getOptionalInputShape();
		$ttsProvider = $this->taskProcessingService->getPreferredProvider(TextToSpeech::ID);
		$ttsOptionalInputShape = $ttsProvider->getOptionalInputShape();

		$optionalInputShapeDefaults = [];

		if (isset($translateOptionalInputShape['model'])) {
			$optionalInputShapeDefaults['translation_model'] = $translateProvider->getOptionalInputShapeDefaults()['model'];
		}

		if (isset($ttsOptionalInputShape['model'])) {
			$optionalInputShapeDefaults['speech_model'] = $ttsProvider->getOptionalInputShapeDefaults()['model'];
		}

		if (isset($ttsOptionalInputShape['voice'])) {
			$optionalInputShapeDefaults['speech_voice'] = $ttsProvider->getOptionalInputShapeDefaults()['voice'];
		}

		if (isset($ttsOptionalInputShape['speed'])) {
			$optionalInputShapeDefaults['speech_speed'] = $ttsProvider->getOptionalInputShapeDefaults()['speed'];
		}

		return $optionalInputShapeDefaults;
	}

	public function getOutputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalOutputShape(): array {
		return [
			'text_input' => new ShapeDescriptor(
				$this->l->t('Audio transcription'),
				$this->l->t('The transcribed audio input'),
				EShapeType::Text,
			),
			'text_output' => new ShapeDescriptor(
				$this->l->t('Text output'),
				$this->l->t('The text translation'),
				EShapeType::Text,
			),
		];
	}

	public function getOptionalOutputShapeEnumValues(): array {
		return [];
	}

	public function process(
		?string $userId, array $input, callable $reportProgress, SynchronousProviderOptions $options = new SynchronousProviderOptions(),
	): array {
		$includeWatermark = $options->getIncludeWatermarks();
		$reportOutput = $options->getReportIntermediateOutput();
		$preferStreaming = $options->getPreferStreaming();

		if (!isset($input['input']) || !$input['input'] instanceof File || !$input['input']->isReadable()) {
			throw new ProcessingException('Invalid input file');
		}

		if (!isset($input['origin_language']) || !is_string($input['origin_language'])) {
			throw new ProcessingException('Invalid origin_language input');
		}
		if (!isset($input['target_language']) || !is_string($input['target_language'])) {
			throw new ProcessingException('Invalid target_language input');
		}

		$translationModel = null;
		if (isset($input['translation_model']) && is_string($input['translation_model'])) {
			$translationModel = $input['translation_model'];
		}

		$ttsModel = null;
		if (isset($input['speech_model']) && is_string($input['speech_model'])) {
			$ttsModel = $input['speech_model'];
		}

		$ttsVoice = null;
		if (isset($input['speech_voice']) && is_string($input['speech_voice'])) {
			$ttsVoice = $input['speech_voice'];
		}

		$ttsSpeed = null;
		if (isset($input['speech_speed']) && is_numeric($input['speech_speed'])) {
			$ttsSpeed = $input['speech_speed'];
		}

		// STT
		try {
			$task = new Task(
				AudioToText::ID,
				['input' => $input['input']->getId()],
				Application::APP_ID . ':internal',
				$userId,
			);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$transcription = $taskOutput['output'];
		} catch (Exception $e) {
			$this->logger->warning('STT sub task failed with: ' . $e->getMessage(), ['exception' => $e]);
			throw new ProcessingException('STT sub task failed with: ' . $e->getMessage());
		}

		if (empty(trim($transcription))) {
			throw new ProcessingException("Empty transcription result from {$input['origin_language']} to {$input['target_language']}");
		}
		$watermarkSuffix = '';
		if ($includeWatermark) {
			if ($userId !== null) {
				$user = $this->userManager->getExistingUser($userId);
				$lang = $this->l10nFactory->getUserLanguage($user);
				$l = $this->l10nFactory->get(Application::APP_ID, $lang);
				$watermarkSuffix = "\n\n" . $l->t('This was generated using Artificial Intelligence.');
			} else {
				$watermarkSuffix = "\n\n" . $this->l->t('This was generated using Artificial Intelligence.');
			}
		}

		$reportProgress(0.3);

		if ($preferStreaming) {
			$running = $reportOutput([
				'text_input' => $transcription . $watermarkSuffix,
			]);
			if (!$running) {
				throw new ProcessingException('Audio translation task cancelled');
			}
		}

		// translation
		try {
			$translationInput = [
				'input' => $transcription,
				'origin_language' => $input['origin_language'],
				'target_language' => $input['target_language'],
			];
			if ($translationModel !== null) {
				$translationInput['translation_model'] = $translationModel;
			}
			$task = new Task(
				TextToTextTranslate::ID,
				$translationInput,
				Application::APP_ID . ':internal',
				$userId,
			);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$translatedText = $taskOutput['output'];
			// report progress
			$reportProgress(0.6);
		} catch (Exception $e) {
			$this->logger->warning('Translation sub task failed with: ' . $e->getMessage(), ['exception' => $e]);
			throw new ProcessingException('Translation sub task failed with: ' . $e->getMessage());
		}

		if ($preferStreaming) {
			$running = $reportOutput([
				'text_input' => $transcription . $watermarkSuffix,
				'text_output' => $translatedText . $watermarkSuffix,
			]);
			if (!$running) {
				throw new ProcessingException('Audio translation task cancelled');
			}
		}

		// TTS
		try {
			$ttsInput = ['input' => $translatedText];
			if ($ttsModel !== null) {
				$ttsInput['model'] = $ttsModel;
			}
			if ($ttsVoice !== null) {
				$ttsInput['voice'] = $ttsVoice;
			}
			if ($ttsSpeed !== null) {
				$ttsInput['speed'] = $ttsSpeed;
			}
			// this provider is not declared if TextToSpeech does not exist so we know it's fine
			/** @psalm-suppress UndefinedClass */
			$task = new Task(
				TextToSpeech::ID,
				$ttsInput,
				Application::APP_ID . ':internal',
				$userId,
			);
			$task->setIncludeWatermark(true);
			$taskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
			$outputAudioFileId = $taskOutput['speech'];

			return [
				'text_input' => $transcription . $watermarkSuffix,
				'text_output' => $translatedText . $watermarkSuffix,
				'audio_output' => $this->taskProcessingService->getOutputFileContent($outputAudioFileId),
			];
		} catch (\Exception $e) {
			$this->logger->warning('Text to speech generation failed with: ' . $e->getMessage(), ['exception' => $e]);
			throw new ProcessingException('Text to speech sub task failed with: ' . $e->getMessage());
		}
	}
}
