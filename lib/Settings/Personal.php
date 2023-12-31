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
		$textProcessingAvailable = $this->textProcessingManager->hasProviders() ?
			$this->config->getAppValue(Application::APP_ID, 'assistant_enabled', '1') === '1' :
			false;
		$assistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';
		
		$textToImagePickerAvailable =  $this->textToImageManager->hasProviders() ?
			$this->config->getAppValue(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1' :
			false;
		$textToImagePickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';
		
		$userConfig = [
			'text_processing_available' => $textProcessingAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImagePickerAvailable,
			'text_to_image_picker_enabled' => $textToImagePickerEnabled,
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
