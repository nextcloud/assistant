<?php

namespace OCA\TpAssistant\Service;

use DateTime;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTask;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;

class NotificationService {

	public function __construct(
		private INotificationManager $notificationManager,
		private IURLGenerator $url,
	) {
	}

	/**
	 * Send a success or failure task result notification
	 *
	 * @param MetaTask $metaTask
	 * @param string|null $customTarget optional notification link target
	 * @param string|null $actionLabel optional label for the notification action button
	 * @param string|null $resultPreview
	 * @return void
	 */
	public function sendNotification(MetaTask $metaTask, ?string $customTarget = null, ?string $actionLabel = null, ?string $resultPreview = null): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'appId' => $metaTask->getAppId(),
			'id' => $metaTask->getId(),
			'inputs' => $metaTask->getInputsAsArray(),
			'target' => $customTarget ?? $this->getDefaultTarget($metaTask),
			'actionLabel' => $actionLabel,
			'result' => $resultPreview,
		];
		$params['taskTypeClass'] = $metaTask->getTaskType();
		$params['taskCategory'] = $metaTask->getCategory();

		$taskSuccessful = $metaTask->getStatus() === Application::STATUS_META_TASK_SUCCESSFUL;

		$subject = $taskSuccessful
			? 'success'
			: 'failure';

		$objectType = $customTarget === null
			? 'task'
			: 'task-with-custom-target';

		$notification->setApp(Application::APP_ID)
			->setUser($metaTask->getUserId())
			->setDateTime(new DateTime())
			->setObject($objectType, (string) ($metaTask->getId() ?? 0))
			->setSubject($subject, $params);

		$manager->notify($notification);
	}

	private function getDefaultTarget(MetaTask $task): string {
		return $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getAssistantTaskResultPage', ['metaTaskId' => $task->getId()]);
	}
}
