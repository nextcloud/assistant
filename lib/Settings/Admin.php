<?php

namespace OCA\Assistant\Settings;

use OCA\Assistant\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToText;

class Admin implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ITaskProcessingManager $taskProcessingManager,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();

		$taskProcessingAvailable = $this->taskProcessingManager->hasProviders();

		$freePromptTaskTypeAvailable = array_key_exists(TextToText::ID, $availableTaskTypes);
		$speechToTextAvailable = array_key_exists(AudioToText::ID, $availableTaskTypes);
		$textToImageAvailable = array_key_exists(TextToImage::ID, $availableTaskTypes);

		$assistantEnabled = $this->config->getAppValue(Application::APP_ID, 'assistant_enabled', '1') === '1';

		$freePromptPickerEnabled = $this->config->getAppValue(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';
		$textToImagePickerEnabled = $this->config->getAppValue(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';
		$maxImageGenerationIdleTime = (int) $this->config->getAppValue(Application::APP_ID, 'max_image_generation_idle_time', (string) Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME);

		$speechToTextEnabled = $this->config->getAppValue(Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1';
		$chattyLLMUserInstructions = $this->config->getAppValue(Application::APP_ID, 'chat_user_instructions', Application::CHAT_USER_INSTRUCTIONS);
		$chattyLLMUserInstructionsTitle = $this->config->getAppValue(Application::APP_ID, 'chat_user_instructions_title', Application::CHAT_USER_INSTRUCTIONS_TITLE);
		$chattyLLMLastNMessages = (int) $this->config->getAppValue(Application::APP_ID, 'chat_last_n_messages', '10');

		$adminConfig = [
			'text_processing_available' => $taskProcessingAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImageAvailable,
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
