<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use DateTime;
use OCA\Assistant\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use OCP\TaskProcessing\Task;

class NotificationService {

	public function __construct(
		private INotificationManager $notificationManager,
		private IURLGenerator $url,
	) {
	}

	/**
	 * Send a success or failure task result notification
	 *
	 * @param Task $task
	 * @param string|null $customTarget optional notification link target
	 * @param string|null $actionLabel optional label for the notification action button
	 * @param string|null $resultPreview
	 * @return void
	 */
	public function sendNotification(Task $task, ?string $customTarget = null, ?string $actionLabel = null, ?string $resultPreview = null): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'appId' => $task->getAppId(),
			'id' => $task->getId(),
			'inputs' => $task->getInput(),
			'target' => $customTarget ?? $this->getDefaultTarget($task),
			'actionLabel' => $actionLabel,
			'result' => $resultPreview,
		];
		$params['taskTypeId'] = $task->getTaskTypeId();

		$taskSuccessful = $task->getStatus() === Task::STATUS_SUCCESSFUL;

		$subject = $taskSuccessful
			? 'success'
			: 'failure';

		$objectType = $customTarget === null
			? 'task'
			: 'task-with-custom-target';

		$notification->setApp(Application::APP_ID)
			->setUser($task->getUserId())
			->setDateTime(new DateTime())
			->setObject($objectType, (string)($task->getId() ?? 0))
			->setSubject($subject, $params);

		$manager->notify($notification);
	}

	private function getDefaultTarget(Task $task): string {
		return $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getAssistantTaskResultPage', ['taskId' => $task->getId()]);
	}
}
