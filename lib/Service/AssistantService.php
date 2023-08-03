<?php

namespace OCA\TPAssistant\Service;

use DateTime;
use OCA\TPAssistant\AppInfo\Application;
use OCP\TextProcessing\Task;
use OCP\Notification\IManager as INotificationManager;

class AssistantService {

	public function __construct(
		string $appName,
		private INotificationManager $notificationManager,
	) {
	}

	public function sendNotification(Task $task): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'appId' => $task->getAppId(),
			'id' => $task->getId(),
			'input' => $task->getInput(),
		];
		$status = $task->getStatus();
		$subject = $status === Task::STATUS_SUCCESSFUL
			? 'success'
			: 'failure';
		$notification->setApp(Application::APP_ID)
			->setUser($task->getUserId())
			->setDateTime(new DateTime())
			->setObject('task', $task->getId())
			->setSubject($subject, $params);

		$manager->notify($notification);
	}

}
