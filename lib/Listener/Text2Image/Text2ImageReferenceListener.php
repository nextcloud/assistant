<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Listener\Text2Image;

use OCA\Assistant\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\TextToImage\IManager;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class Text2ImageReferenceListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private ?string $userId,
		private IManager $manager
	) {
	}

	public function handle(Event $event): void {

		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1' &&
			$this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1') {

			// Double check that atleast one provider is registered
			if ($this->manager->hasProviders()) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-imageGenerationReference');
			}
		}
	}
}
