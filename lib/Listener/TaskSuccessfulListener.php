<?php

namespace OCA\TPAssistant\Listener;

use OCA\TPAssistant\AppInfo\Application;
use OCA\TPAssistant\Event\BeforeAssistantNotificationEvent;
use OCA\TPAssistant\Service\AssistantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\TextProcessing\Events\TaskSuccessfulEvent;

class TaskSuccessfulListener implements IEventListener {

	public function __construct(
		private AssistantService $assistantService,
		private IEventDispatcher $eventDispatcher,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskSuccessfulEvent) {
			return;
		}

		$task = $event->getTask();
		error_log('Task successful ' . $task->getId());
		if ($task->getUserId() === null) {
			return;
		}

		$notificationTarget = null;

		// we dispatch an event to ask the app that scheduled the task if it wants a notification
		// and what the target should be
		if ($task->getAppId() !== Application::APP_ID) {
			$beforeAssistantNotificationEvent = new BeforeAssistantNotificationEvent($task);
			$this->eventDispatcher->dispatchTyped($beforeAssistantNotificationEvent);
			if (!$beforeAssistantNotificationEvent->getWantsNotification()) {
				return;
			}
			$notificationTarget = $beforeAssistantNotificationEvent->getNotificationTarget();
		}

		$this->assistantService->sendNotification($task, $notificationTarget);
	}
}
