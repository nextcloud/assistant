<?php

namespace OCA\TPAssistant\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TextProcessing\Events\TaskSuccessfulEvent;

class TaskSuccessfulListener implements IEventListener {

	public function __construct(
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskSuccessfulEvent) {
			return;
		}

		error_log('Task successful');
	}
}
