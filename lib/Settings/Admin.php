<?php

namespace OCA\Assistant\Settings;

use OCA\Assistant\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\SpeechToText\ISpeechToTextManager;
use OCP\TextProcessing\FreePromptTaskType;
use OCP\TextProcessing\IManager as ITextProcessingManager;

use OCP\TextToImage\IManager as ITextToImageManager;

class Admin implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ITextToImageManager $textToImageManager,
		private ITextProcessingManager $textProcessingManager,
		private ISpeechToTextManager $speechToTextManager,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$textProcessingAvailable = $this->textProcessingManager->hasProviders();
		$freePromptTaskTypeAvailable = in_array(FreePromptTaskType::class, $this->textProcessingManager->getAvailableTaskTypes());
		$assistantEnabled = $this->config->getAppValue(Application::APP_ID, 'assistant_enabled', '1') === '1';
		$textToImagePickerAvailable = $this->textToImageManager->hasProviders();
		$textToImagePickerEnabled = $this->config->getAppValue(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';
		$maxImageGenerationIdleTime = (int) $this->config->getAppValue(Application::APP_ID, 'max_image_generation_idle_time', (string) Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME);
		$freePromptPickerEnabled = $this->config->getAppValue(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';
		$speechToTextAvailable = $this->speechToTextManager->hasProviders();
		$speechToTextEnabled = $this->config->getAppValue(Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1';
		$chattyLLMUserInstructions = $this->config->getAppValue(Application::APP_ID, 'chat_user_instructions', $this->l10n->t(Application::CHAT_USER_INSTRUCTIONS));
		$chattyLLMUserInstructionsTitle = $this->config->getAppValue(Application::APP_ID, 'chat_user_instructions_title', $this->l10n->t(Application::CHAT_USER_INSTRUCTIONS_TITLE));
		$chattyLLMLastNMessages = (int) $this->config->getAppValue(Application::APP_ID, 'chat_last_n_messages', '10');

		$adminConfig = [
			'text_processing_available' => $textProcessingAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImagePickerAvailable,
			'text_to_image_picker_enabled' => $textToImagePickerEnabled,
			'max_image_generation_idle_time' => $maxImageGenerationIdleTime,
			'free_prompt_task_type_available' => $freePromptTaskTypeAvailable,
			'free_prompt_picker_enabled' => $freePromptPickerEnabled,
			'speech_to_text_picker_available' => $speechToTextAvailable,
			'speech_to_text_picker_enabled' => $speechToTextEnabled,
			'chat_user_instructions' => $chattyLLMUserInstructions,
			'chat_user_instructions_title' => $chattyLLMUserInstructionsTitle,
			'chat_last_n_messages' => $chattyLLMLastNMessages,
		];
		$this->initialStateService->provideInitialState('admin-config', $adminConfig);

		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'ai';
	}

	public function getPriority(): int {
		return 10;
	}
}
