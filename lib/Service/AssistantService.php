<?php

namespace OCA\TPAssistant\Service;

use DateTime;
use OCA\TPAssistant\AppInfo\Application;
use OCP\Common\Exception\NotFoundException;
use OCP\PreConditionNotMetException;
use OCP\TextProcessing\IManager as ITextProcessingManager;
use OCP\TextProcessing\Task as TextProcessingTask;
use OCP\TextToImage\Task as TextToImageTask;
use OCP\Notification\IManager as INotificationManager;

class AssistantService {

	public function __construct(
		string $appName,
		private INotificationManager $notificationManager,
		private ITextProcessingManager $textProcessingManager,
	) {
	}

	/**
	 * Send a success or failure task result notification
	 *
	 * @param TextProcessingTask|TextToImageTask $task
	 * @param string|null $target optional notification link target
	 * @param string|null $actionLabel optional label for the notification action button
	 * @return void
	 */
	public function sendNotification(TextProcessingTask|TextToImageTask $task, ?string $target = null, ?string $actionLabel = null): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'appId' => $task->getAppId(),
			'id' => $task->getId(),
			'input' => $task->getInput(),
			'target' => $target,
			'actionLabel' => $actionLabel,			
		];
		if ($task instanceof TextToImageTask) {
			$params['taskType'] = Application::TASK_TYPE_TEXT_TO_IMAGE;
			$subject = $task->getStatus() === TextToImageTask::STATUS_SUCCESSFUL
				? 'success'
				: 'failure';
		} else {
			$params['taskType'] = Application::TASK_TYPE_TEXT_GEN;
			$params['textTaskTypeClass'] = $task->getType();
			$subject = $task->getStatus() === TextProcessingTask::STATUS_SUCCESSFUL
				? 'success'
				: 'failure';
		}

		$objectType = $target === null
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
	 * @return TextProcessingTask
	 */
	public function getTextProcessingTask(?string $userId, int $taskId): ?TextProcessingTask {
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

	/**
	 * @param string $type
	 * @param string $input
	 * @param string $appId
	 * @param string|null $userId
	 * @param string $identifier
	 * @return TextProcessingTask
	 * @throws PreConditionNotMetException
	 */
	public function runTextProcessingTask(string $type, string $input, string $appId, ?string $userId, string $identifier): TextProcessingTask {
		$task = new TextProcessingTask($type, $input, $appId, $userId, $identifier);
		$this->textProcessingManager->runTask($task);
		return $task;
	}

	/**
	 * @param string $type
	 * @param string $input
	 * @param string $appId
	 * @param string|null $userId
	 * @param string $identifier
	 * @return TextProcessingTask
	 * @throws PreConditionNotMetException
	 */
	public function runOrScheduleTextProcessingTask(string $type, string $input, string $appId, ?string $userId, string $identifier): TextProcessingTask {
		$task = new TextProcessingTask($type, $input, $appId, $userId, $identifier);
		$this->textProcessingManager->runOrScheduleTask($task);
		return $task;
	}
}
