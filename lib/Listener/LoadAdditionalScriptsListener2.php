<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\AssistantService;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Util;

/**
 * @template-extends IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadAdditionalScriptsListener2 implements IEventListener {

	public function __construct(
		private readonly IConfig $config,
		private readonly IInitialState $initialState,
		private readonly AssistantService $assistantService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'disableFilesNewMenuPlugin', '0') === '1') {
			return;
		}

		$taskTypes = $this->assistantService->getAvailableTaskTypes();
		$hasText2Image = false;
		foreach ($taskTypes as $taskType) {
			if ($taskType['id'] === 'core:text2image') {
				$hasText2Image = true;
				break;
			}
		}

		$this->initialState->provideInitialState('new-file-generate-image', [
			'hasText2Image' => $hasText2Image,
			'taskTypes' => $taskTypes,
		]);

		Util::addScript(Application::APP_ID, Application::APP_ID . '-filesNewMenu');
	}
}
