<?php

namespace OCA\TpAssistant\Listener;

use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Event\BeforeAssistantNotificationEvent;
use OCA\TpAssistant\Service\AssistantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\TextProcessing\Events\TaskFailedEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCA\TpAssistant\Db\TaskMapper;

/**
 * @template-implements IEventListener<Event>
 */
class TaskFailedListener implements IEventListener {

	public function __construct(
		private AssistantService $assistantService,
		private IEventDispatcher $eventDispatcher,
		private TaskMapper $taskMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskFailedEvent) {
			return;
		}

		$task = $event->getTask();
		error_log('Task failed ' . $task->getId());
		if ($task->getUserId() === null) {
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

		try {
			$assistantTask = $this->taskMapper->getTaskByOcpTaskIdAndModality($task->getId(), Application::TASK_TYPE_TEXT_GEN);
		} catch (DoesNotExistException $e) {
			// Not an assistant task
			return;
		}

		// Update task status and output:
		$assistantTask->setStatus($task->getStatus());
		$assistantTask->setOutput($task->getOutput());
		$assistantTask = $this->taskMapper->update($assistantTask);

		$this->assistantService->sendNotification($assistantTask, $notificationTarget, $notificationActionLabel);
	}
}
