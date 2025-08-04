<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Settings;

use OCA\Assistant\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToText;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
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

		$assistantEnabled = $this->appConfig->getValueString(Application::APP_ID, 'assistant_enabled', '1') === '1';

		$freePromptPickerEnabled = $this->appConfig->getValueString(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';
		$textToImagePickerEnabled = $this->appConfig->getValueString(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';
		$textToStickerPickerEnabled = $this->appConfig->getValueString(Application::APP_ID, 'text_to_sticker_picker_enabled', '1') === '1';

		$speechToTextEnabled = $this->appConfig->getValueString(Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1';
		$chattyLLMUserInstructions = $this->appConfig->getValueString(Application::APP_ID, 'chat_user_instructions', Application::CHAT_USER_INSTRUCTIONS) ?: Application::CHAT_USER_INSTRUCTIONS;
		$chattyLLMUserInstructionsTitle = $this->appConfig->getValueString(Application::APP_ID, 'chat_user_instructions_title', Application::CHAT_USER_INSTRUCTIONS_TITLE) ?: Application::CHAT_USER_INSTRUCTIONS_TITLE;
		$chattyLLMLastNMessages = (int)$this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10');

		$adminConfig = [
			'text_processing_available' => $taskProcessingAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImageAvailable,
			'text_to_image_picker_enabled' => $textToImagePickerEnabled,
			'text_to_sticker_picker_enabled' => $textToStickerPickerEnabled,
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
