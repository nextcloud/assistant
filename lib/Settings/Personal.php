<?php
namespace OCA\TPAssistant\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCA\TPAssistant\AppInfo\Application;
use OCP\TextToImage\IManager;
use OCP\TextProcessing\IManager as ITextProcessingManager;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId,
		private IManager $textToImageManager,
		private ITextProcessingManager $textProcessingManager
		) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$textProcessingAvailable = $this->textProcessingManager->hasProviders();

		$assistantAvailable = $textProcessingAvailable ?
			$this->config->getAppValue(Application::APP_ID, 'assistant_enabled', '1') === '1' :
			false;
		$assistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';
		
		$textToImagePickerAvailable =  $this->textToImageManager->hasProviders() ?
			$this->config->getAppValue(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1' :
			false;
		$textToImagePickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';

		$freePromptPickerAvailable = $textProcessingAvailable ?
			$this->config->getAppValue(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1' :
			false;
		$freePromptPickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';
		
		$userConfig = [
			'assistant_available' => $assistantAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImagePickerAvailable,
			'text_to_image_picker_enabled' => $textToImagePickerEnabled,
			'free_prompt_picker_available' => $freePromptPickerAvailable,
			'free_prompt_picker_enabled' => $freePromptPickerEnabled,
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
