<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant;

use OCA\Assistant\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;
use OCP\IAppConfig;
use OCP\IConfig;

class Capabilities implements IPublicCapability {

	public function __construct(
		private IAppManager $appManager,
		private IConfig $config,
		private IAppConfig $appConfig,
		private ?string $userId,
	) {
	}

	/**
	 * @return array{
	 *     assistant: array{
	 *         version: string,
	 *         enabled?: bool
	 *     }
	 * }
	 */
	public function getCapabilities(): array {
		$appVersion = $this->appManager->getAppVersion(Application::APP_ID);
		$capability = [
			Application::APP_ID => [
				'version' => $appVersion,
			],
		];
		if ($this->userId !== null) {
			$adminAssistantEnabled = $this->appConfig->getValueString(Application::APP_ID, 'assistant_enabled', '1') === '1';
			$userAssistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';
			$assistantEnabled = $adminAssistantEnabled && $userAssistantEnabled;
			$capability[Application::APP_ID]['enabled'] = $assistantEnabled;
		}
		return $capability;
	}
}
