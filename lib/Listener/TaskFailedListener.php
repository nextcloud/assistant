<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\TaskNotificationMapper;
use OCA\Assistant\Event\BeforeAssistantNotificationEvent;
use OCA\Assistant\Service\NotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\TaskProcessing\Events\TaskFailedEvent;

/**
 * @template-implements IEventListener<Event>
 */
class TaskFailedListener implements IEventListener {

	public function __construct(
		private TaskNotificationMapper $taskNotificationMapper,
		private NotificationService $notificationService,
		private IEventDispatcher $eventDispatcher,
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

		if ($this->taskNotificationMapper->getByTaskId($task->getId()) === null) {
			return;
		}

		$notificationTarget = null;
		$notificationActionLabel = null;

		// we dispatch an event to ask the app that scheduled the task if it wants a notification
		// and what the target should be
		if ($task->getAppId() !== Application::APP_ID) {
			$beforeAssistantNotificationEvent = new BeforeAssistantNotificationEvent($task);
			$this->eventDispatcher->dispatchTyped($beforeAssistantNotificationEvent);
			if (!$beforeAssistantNotificationEvent->getWantsNotification()) {
				return;
			}
			$notificationTarget = $beforeAssistantNotificationEvent->getNotificationTarget();
			$notificationActionLabel = $beforeAssistantNotificationEvent->getNotificationActionLabel();
		}

		$this->notificationService->sendNotification($task, $notificationTarget, $notificationActionLabel);
		$this->taskNotificationMapper->deleteByTaskId($task->getId());
	}
}
