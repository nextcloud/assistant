<?php

namespace OCA\TpAssistant\Service;

use DateTime;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTask;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use OCP\TextProcessing\Task as TextProcessingTask;
use OCP\TextToImage\Task as TextToImageTask;

class NotificationService {

	public function __construct(
		private INotificationManager $notificationManager,
		private IURLGenerator $url,
	) {
	}

	/**
	 * Send a success or failure task result notification
	 *
	 * @param MetaTask $task
	 * @param string|null $customTarget optional notification link target
	 * @param string|null $actionLabel optional label for the notification action button
	 * @param string|null $resultPreview
	 * @return void
	 */
	public function sendNotification(MetaTask $task, ?string $customTarget = null, ?string $actionLabel = null, ?string $resultPreview = null): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'appId' => $task->getAppId(),
			'id' => $task->getId(),
			'inputs' => $task->getInputsAsArray(),
			'target' => $customTarget ?? $this->getDefaultTarget($task),
			'actionLabel' => $actionLabel,
			'result' => $resultPreview,
		];
		$params['taskTypeClass'] = $task->getTaskType();
		$params['taskCategory'] = $task->getCategory();

		switch ($task->getCategory()) {
			case Application::TASK_CATEGORY_TEXT_TO_IMAGE:
				{
					$taskSuccessful = $task->getStatus() === TextToImageTask::STATUS_SUCCESSFUL;
					break;
				}
			case Application::TASK_CATEGORY_TEXT_GEN:
				{
					$taskSuccessful = $task->getStatus() === TextProcessingTask::STATUS_SUCCESSFUL;
					break;
				}
			case Application::TASK_CATEGORY_SPEECH_TO_TEXT:
				{
					$taskSuccessful = $task->getStatus() === Application::STT_TASK_SUCCESSFUL;
					break;
				}
			default:
				{
					$taskSuccessful = false;
					break;
				}
		}

		$subject = $taskSuccessful
			? 'success'
			: 'failure';

		$objectType = $customTarget === null
			? 'task'
			: 'task-with-custom-target';

		$notification->setApp(Application::APP_ID)
			->setUser($task->getUserId())
			->setDateTime(new DateTime())
			->setObject($objectType, (string) ($task->getId() ?? 0))
			->setSubject($subject, $params);

		$manager->notify($notification);
	}

	private function getDefaultTarget(MetaTask $task): string {
		return $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getAssistantTaskResultPage', ['metaTaskId' => $task->getId()]);
	}
}
