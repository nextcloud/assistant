<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener\Text2Image;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\TaskProcessing\TextToStickerTaskType;
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
class Text2StickerListener implements IEventListener {
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

		if ($this->appConfig->getValueString(Application::APP_ID, 'text_to_sticker_picker_enabled', '1') === '1'
			&& $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_sticker_picker_enabled', '1') === '1') {

			// Double check that all necessary task types are available
			// For some reason, taskProcessingManager->getAvailableTaskTypeIds does not return all the task types here
			// most likely because all providers are not registered when RenderReferenceEvent is fired
			$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
			$textToImageAvailable = array_key_exists(TextToImage::ID, $availableTaskTypes);
			$textToStickerAvailable = array_key_exists(TextToStickerTaskType::ID, $availableTaskTypes);
			if ($textToImageAvailable && $textToStickerAvailable) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-stickerGeneration');
			}
		}
	}
}
