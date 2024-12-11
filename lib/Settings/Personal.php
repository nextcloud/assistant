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
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\TaskProcessing\Exception\Exception;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToText;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private IInitialState $initialStateService,
		private ?string $userId,
		private ITaskProcessingManager $taskProcessingManager,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();

		$taskProcessingAvailable = $this->taskProcessingManager->hasProviders();

		$freePromptTaskTypeAvailable = in_array(TextToText::ID, $availableTaskTypes);
		$speechToTextAvailable = in_array(AudioToText::ID, $availableTaskTypes);
		$textToImageAvailable = in_array(TextToImage::ID, $availableTaskTypes);

		$assistantAvailable = $taskProcessingAvailable && $this->appConfig->getValueString(Application::APP_ID, 'assistant_enabled', '1') === '1';
		$assistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';

		$textToImagePickerAvailable = $textToImageAvailable && $this->appConfig->getValueString(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';
		$textToImagePickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';

		$freePromptPickerAvailable = $freePromptTaskTypeAvailable && $this->appConfig->getValueString(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';
		$freePromptPickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';

		$speechToTextPickerAvailable = $speechToTextAvailable && $this->appConfig->getValueString(Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1';
		$speechToTextPickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1';

		$userConfig = [
			'assistant_available' => $assistantAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImagePickerAvailable,
			'text_to_image_picker_enabled' => $textToImagePickerEnabled,
			'free_prompt_picker_available' => $freePromptPickerAvailable,
			'free_prompt_picker_enabled' => $freePromptPickerEnabled,
			'speech_to_text_picker_available' => $speechToTextPickerAvailable,
			'speech_to_text_picker_enabled' => $speechToTextPickerEnabled,
		];
		$this->initialStateService->provideInitialState('config', $userConfig);

		$availableProviders = [];
		foreach ($availableTaskTypes as $taskTypeId => $availableTaskType) {
			try {
				$provider = $this->taskProcessingManager->getPreferredProvider($taskTypeId);
				$availableProviders[$availableTaskType['name']] = $provider->getName();
			} catch (Exception $e) {
				// pass
			}
		}
		$this->initialStateService->provideInitialState('availableProviders', $availableProviders);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'ai';
	}

	public function getPriority(): int {
		return 10;
	}
}
