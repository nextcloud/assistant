<?php

namespace OCA\TPAssistant\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TextProcessing\Events\TaskFailedEvent;

class TaskFailedListener implements IEventListener {

	public function __construct(
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskFailedEvent) {
			return;
		}

		error_log('Task failed');
	}
}
