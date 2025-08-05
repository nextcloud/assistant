<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\Service\NotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\TaskProcessing\Events\TaskFailedEvent;

/**
 * @template-implements IEventListener<Event>
 */
class NewFileMenuTaskFailedListener implements IEventListener {

	public function __construct(
		private NotificationService $notificationService,
		private IRootFolder $rootFolder,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskFailedEvent) {
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
		$userFolder = $this->rootFolder->getUserFolder($task->getUserId());
		$directory = $userFolder->getFirstNodeById($directoryId);
		$this->notificationService->sendNewImageFileNotification(
			$task->getUserId(), $task->getId(),
			$directoryId, $directory->getName(), $userFolder->getRelativePath($directory->getPath()),
		);
	}
}
