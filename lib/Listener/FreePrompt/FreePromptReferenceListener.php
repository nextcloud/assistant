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
use OCP\TextProcessing\FreePromptTaskType;
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

		$pickerEnabled = true;
		
		$taskTypes = $this->textProcessingManager->getAvailableTaskTypes();
		if (!in_array(FreePromptTaskType::class, $taskTypes)) {
			$this->logger->debug('FreePromptTaskType not available');
			$pickerEnabled = false;
		}

		if ($this->userId === null) {
			$isAdmin = false;
		} else {
			$isAdmin = $this->iGroupManager->isAdmin($this->userId);
		}

		$features = [
			'picker_enabled' => $pickerEnabled,
			'is_admin' => $isAdmin,
		];

		$this->initialState->provideInitialState('text-features', $features);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-textReference');
	}
}
