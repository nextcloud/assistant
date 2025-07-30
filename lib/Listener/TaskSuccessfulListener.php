<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\TaskNotificationMapper;
use OCA\Assistant\Event\BeforeAssistantNotificationEvent;
use OCA\Assistant\Service\AssistantService;
use OCA\Assistant\Service\NotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class TaskSuccessfulListener implements IEventListener {

	public function __construct(
		private TaskNotificationMapper $taskNotificationMapper,
		private NotificationService $notificationService,
		private IEventDispatcher $eventDispatcher,
		private AssistantService $assistantService,
		private LoggerInterface $logger,
		private IUrlGenerator $url,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TaskSuccessfulEvent) {
			return;
		}

		$task = $event->getTask();
		if ($task->getUserId() === null) {
			return;
		}

		$customIdPattern = '/^new-image-file:(\d+)$/';
		$hasTargetDirectory = preg_match($customIdPattern, $task->getCustomId(), $matches) === 1;

		// For tasks with customId "new-image-file:<directoryIdNumber>" we always send a notification
		if ($this->taskNotificationMapper->getByTaskId($task->getId()) === null && !$hasTargetDirectory) {
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

		if ($hasTargetDirectory) {
			$directoryId = (int)$matches[1];
			$fileId = (int)$task->getOutput()['images'][0];
			try {
				$file = $this->assistantService->saveNewFileMenuActionFile($task->getUserId(), $task->getId(), $fileId, $directoryId);
				$notificationTarget = $this->url->linkToRouteAbsolute(
					'files.viewcontroller.showFile',
					[
						'fileid' => $file->getId(),
						'opendetails' => 'true',
						'openfile' => 'false',
					],
				);
			} catch (\Exception $e) {
				$this->logger->error('TaskSuccessfulListener: Failed to save new file menu action file.', [
					'task' => $task->jsonSerialize(),
					'exception' => $e,
				]);
			}
		}

		$this->notificationService->sendNotification($task, $notificationTarget, $notificationActionLabel);

		if (!$hasTargetDirectory) {
			$this->taskNotificationMapper->deleteByTaskId($task->getId());
		}
	}
}
