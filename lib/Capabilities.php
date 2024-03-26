<?php

declare(strict_types=1);

namespace OCA\Assistant;

use OCA\Assistant\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;
use OCP\IConfig;

class Capabilities implements IPublicCapability {

	public function __construct(
		private IAppManager $appManager,
		private IConfig $config,
		private ?string $userId,
	) {
	}

	/**
	 * @return array<string, array<string, bool|string>>
	 */
	public function getCapabilities(): array {
		$appVersion = $this->appManager->getAppVersion(Application::APP_ID);
		$capability = [
			Application::APP_ID => [
				'version' => $appVersion,
			],
		];
		if ($this->userId !== null) {
			$adminAssistantEnabled = $this->config->getAppValue(Application::APP_ID, 'assistant_enabled', '1') === '1';
			$userAssistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';
			$assistantEnabled = $adminAssistantEnabled && $userAssistantEnabled;
			$capability[Application::APP_ID]['enabled'] = $assistantEnabled;
		}
		return $capability;
	}
}
