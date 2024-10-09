<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Listener\FreePrompt;

use OCA\Assistant\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\TextToText;
use OCP\Util;

/**
 * @implements IEventListener<RenderReferenceEvent>
 */
class FreePromptReferenceListener implements IEventListener {
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

		if ($this->appConfig->getValueString(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1'
			&& $this->config->getUserValue($this->userId, Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1') {

			// Double check that at least one provider is registered
			$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
			$freePromptTaskTypeAvailable = array_key_exists(TextToText::ID, $availableTaskTypes);
			if ($freePromptTaskTypeAvailable) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-textGenerationReference');
			}
		}
	}
}
