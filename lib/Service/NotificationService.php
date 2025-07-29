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
			'target' => $customTarget ?? $this->getDefaultTarget($task->getId()),
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

	private function getDefaultTarget(int $taskId): string {
		return $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getAssistantTaskResultPage', ['taskId' => $taskId]);
	}

	public function sendFileActionNotification(
		string $userId, string $taskTypeId, int $taskId,
		int $sourceFileId, string $sourceFileName, string $sourceFilePath,
		?int $targetFileId = null, ?string $targetFileName = null, ?string $targetFilePath = null,
	): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'source_file_id' => $sourceFileId,
			'source_file_name' => $sourceFileName,
			'source_file_path' => $sourceFilePath,
			'target_file_id' => $targetFileId,
			'target_file_name' => $targetFileName,
			'target_file_path' => $targetFilePath,
			'task_type_id' => $taskTypeId,
			'task_id' => $taskId,
			'target' => $this->getDefaultTarget($taskId),
		];
		$taskSuccessful = $targetFileId !== null && $targetFileName !== null;

		$subject = $taskSuccessful
			? 'file_action_success'
			: 'file_action_failure';

		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setDateTime(new DateTime())
			->setObject('task', $taskId)
			->setSubject($subject, $params);

		$manager->notify($notification);
	}
}
