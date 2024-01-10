<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TPAssistant\Listener\FreePrompt;

use OCA\TPAssistant\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\TextProcessing\IManager;
use OCP\Util;

use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<RenderReferenceEvent>
 */
class FreePromptReferenceListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private IInitialState $initialState,
		private ?string $userId,
		private LoggerInterface $logger,
		private IGroupManager $iGroupManager,
		private IManager $textProcessingManager,
	) {

	}

	public function handle(Event $event): void {

		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1' &&
			$this->config->getUserValue($this->userId, Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1') {
				
			// Double check that atleast one provider is registered
			if ($this->textProcessingManager->hasProviders()) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-textGenerationReference');
			}
		}
	}
}
