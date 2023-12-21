<?php
namespace OCA\TPAssistant\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCA\TPAssistant\AppInfo\Application;
use OCP\TextToImage\IManager as ITextToImageManager;
use OCP\TextProcessing\IManager as ITextProcessingManager;

class Admin implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ITextToImageManager $textToImageManager,
		private ITextProcessingManager $textProcessingManager
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$textProcessingAvailable = $this->textProcessingManager->hasProviders();
		$assistantEnabled = $this->config->getAppValue(Application::APP_ID, 'assistant_enabled', '1') === '1';
		$textToImagePickerAvailable =  $this->textToImageManager->hasProviders();
		$textToImagePickerEnabled = $this->config->getAppValue(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';
		$maxImageGenerationIdleTime = $this->config->getAppValue(Application::APP_ID, 'max_image_generation_idle_time', Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME);
		$freePromptPickerEnabled = $this->config->getAppValue(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';

		$adminConfig = [
			'text_processing_available' => $textProcessingAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImagePickerAvailable,
			'text_to_image_picker_enabled' => $textToImagePickerEnabled,
			'max_image_generation_idle_time' => $maxImageGenerationIdleTime,
			'free_prompt_picker_enabled' => $freePromptPickerEnabled,
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
