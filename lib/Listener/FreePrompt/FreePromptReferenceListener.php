<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Listener\FreePrompt;

use OCA\TpAssistant\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\TextProcessing\IManager;
use OCP\Util;

/**
 * @implements IEventListener<RenderReferenceEvent>
 */
class FreePromptReferenceListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private ?string $userId,
		private IManager $textProcessingManager,
	) {

	}

	public function handle(Event $event): void {

		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1' &&
			$this->config->getUserValue($this->userId, Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1') {

			// Double check that at least one provider is registered
			if ($this->textProcessingManager->hasProviders()) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-textGenerationReference');
			}
		}
	}
}
