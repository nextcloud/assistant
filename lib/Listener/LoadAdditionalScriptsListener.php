<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use OCP\Util;

/**
 * @implements IEventListener<Event>
 */
class LoadAdditionalScriptsListener implements IEventListener {

	public function __construct(
		private IAppConfig $appConfig,
		private IInitialState $initialStateService,
		private ITaskProcessingManager $taskProcessingManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();

		// file actions
		$summarizeAvailable = array_key_exists(TextToTextSummary::ID, $availableTaskTypes);
		$sttAvailable = array_key_exists(AudioToText::ID, $availableTaskTypes);
		$ttsAvailable = class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech')
			&& array_key_exists(\OCP\TaskProcessing\TaskTypes\TextToSpeech::ID, $availableTaskTypes);
		$this->initialStateService->provideInitialState('stt-available', $sttAvailable);
		$this->initialStateService->provideInitialState('tts-available', $ttsAvailable);
		$this->initialStateService->provideInitialState('summarize-available', $summarizeAvailable);
		Util::addInitScript(Application::APP_ID, Application::APP_ID . '-fileActions');

		// New file menu to generate images
		$isNewFileMenuEnabled = $this->appConfig->getValueInt(Application::APP_ID, 'newFileMenuPlugin', 1) === 1;
		if ($isNewFileMenuEnabled) {
			$hasText2Image = array_key_exists(TextToImage::ID, $availableTaskTypes);
			$this->initialStateService->provideInitialState('new-file-generate-image', [
				'hasText2Image' => $hasText2Image,
			]);
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesNewMenu');
		}
	}
}
