<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener\SpeechToText;

use OCA\Assistant\AppInfo\Application;

use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class SpeechToTextReferenceListener implements IEventListener {
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
		if ($this->appConfig->getValueString(Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1'
			&& ($this->userId === null || $this->config->getUserValue($this->userId, Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1')) {

			// Double check that at least one provider is registered
			$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
			$speechToTextAvailable = array_key_exists(AudioToText::ID, $availableTaskTypes);
			if ($speechToTextAvailable) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-speechToTextReference');
			}
		}
	}
}
