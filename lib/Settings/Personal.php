<?php

namespace OCA\Assistant\Settings;

use OCA\Assistant\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\SpeechToText\ISpeechToTextManager;
use OCP\TextProcessing\FreePromptTaskType;
use OCP\TextProcessing\IManager as ITextProcessingManager;

use OCP\TextToImage\IManager;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId,
		private IManager $textToImageManager,
		private ITextProcessingManager $textProcessingManager,
		private ISpeechToTextManager $speechToTextManager,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$textProcessingAvailable = $this->textProcessingManager->hasProviders();
		$freePromptTaskTypeAvailable = in_array(FreePromptTaskType::class, $this->textProcessingManager->getAvailableTaskTypes());
		$speechToTextAvailable = $this->speechToTextManager->hasProviders();

		$assistantAvailable = $textProcessingAvailable && $this->config->getAppValue(Application::APP_ID, 'assistant_enabled', '1') === '1';
		$assistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';

		$textToImagePickerAvailable = $this->textToImageManager->hasProviders() && $this->config->getAppValue(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';

		$textToImagePickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';

		$freePromptPickerAvailable = $freePromptTaskTypeAvailable && $this->config->getAppValue(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';
		$freePromptPickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';

		$speechToTextPickerAvailable = $speechToTextAvailable && $this->config->getAppValue(Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1';
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
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'ai';
	}

	public function getPriority(): int {
		return 10;
	}
}
