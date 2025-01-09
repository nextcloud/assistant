<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener\Text2Image;

use OCA\Assistant\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class Text2ImageReferenceListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private ?string $userId,
		private ITaskProcessingManager $taskProcessingManager,
	) {
	}

	public function handle(Event $event): void {

		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		if ($this->appConfig->getValueString(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1'
			&& $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1') {

			// Double check that atleast one provider is registered
			$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
			$textToImageAvailable = array_key_exists(TextToImage::ID, $availableTaskTypes);
			if ($textToImageAvailable) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-imageGenerationReference');
			}
		}
	}
}
