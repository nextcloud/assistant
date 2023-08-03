<?php

namespace OCA\TPAssistant\Listener;

use OCA\TPAssistant\Service\AssistantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TextProcessing\Events\TaskSuccessfulEvent;

class TaskSuccessfulListener implements IEventListener {

	public function __construct(
		private AssistantService $assistantService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskSuccessfulEvent) {
			return;
		}

		$task = $event->getTask();
		$this->assistantService->sendNotification($task);
		error_log('Task successful');
	}
}
