<?php

namespace OCA\TPAssistant\Service;

use DateTime;
use OCA\TPAssistant\AppInfo\Application;
use OCP\Common\Exception\NotFoundException;
use OCP\TextProcessing\IManager as ITextProcessingManager;
use OCP\TextProcessing\Task;
use OCP\Notification\IManager as INotificationManager;

class AssistantService {

	public function __construct(
		string $appName,
		private INotificationManager $notificationManager,
		private ITextProcessingManager $textProcessingManager,
	) {
	}

	public function sendNotification(Task $task, ?string $target): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'appId' => $task->getAppId(),
			'id' => $task->getId(),
			'input' => $task->getInput(),
			'target' => $target,
		];
		$status = $task->getStatus();
		$subject = $status === Task::STATUS_SUCCESSFUL
			? 'success'
			: 'failure';
		$objectType = ($task->getAppId() === Application::APP_ID || $target === null)
			? 'task'
			: 'task-with-custom-target';
		$notification->setApp(Application::APP_ID)
			->setUser($task->getUserId())
			->setDateTime(new DateTime())
			->setObject($objectType, $task->getId())
			->setSubject($subject, $params);

		$manager->notify($notification);
	}

	/**
	 * @param string|null $userId
	 * @param int $taskId
	 * @return Task
	 */
	public function getTask(?string $userId, int $taskId): ?Task {
		try {
			$task = $this->textProcessingManager->getTask($taskId);
		} catch (NotFoundException | \RuntimeException $e) {
			return null;
		}
		if ($task->getUserId() !== $userId) {
			return null;
		}
		return $task;
	}
}
