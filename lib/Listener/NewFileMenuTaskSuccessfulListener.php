<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\Service\AssistantService;
use OCA\Assistant\Service\NotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class NewFileMenuTaskSuccessfulListener implements IEventListener {

	public function __construct(
		private NotificationService $notificationService,
		private AssistantService $assistantService,
		private LoggerInterface $logger,
		private IUrlGenerator $url,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskSuccessfulEvent) {
			return;
		}

		$task = $event->getTask();
		if ($task->getUserId() === null) {
			return;
		}

		$customIdPattern = '/^new-image-file:(\d+)$/';
		$isNewImageFileAction = preg_match($customIdPattern, $task->getCustomId(), $matches) === 1;

		// For tasks with customId "new-image-file:<directoryIdNumber>" we always send a notification
		if (!$isNewImageFileAction) {
			return;
		}

		$directoryId = (int)$matches[1];
		$fileId = (int)$task->getOutput()['images'][0];
		try {
			$file = $this->assistantService->saveNewFileMenuActionFile($task->getUserId(), $task->getId(), $fileId, $directoryId);
			$notificationTarget = $this->url->linkToRouteAbsolute(
				'files.viewcontroller.showFile',
				[
					'fileid' => $file->getId(),
					'opendetails' => 'true',
					'openfile' => 'false',
				],
			);
			$this->notificationService->sendNotification($task, $notificationTarget);
		} catch (\Exception $e) {
			$this->logger->error('TaskSuccessfulListener: Failed to save new file menu action file.', [
				'task' => $task->jsonSerialize(),
				'exception' => $e,
			]);
		}

	}
}
