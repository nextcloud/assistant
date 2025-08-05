<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\Service\NotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TaskProcessing\Events\TaskFailedEvent;

/**
 * @template-implements IEventListener<Event>
 */
class NewFileMenuTaskFailedListener implements IEventListener {

	public function __construct(
		private NotificationService $notificationService,
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

		$notificationTarget = null;
		$notificationActionLabel = null;

		$this->notificationService->sendNotification($task, $notificationTarget, $notificationActionLabel);
	}
}
