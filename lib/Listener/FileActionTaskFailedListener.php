<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<TaskFailedEvent>
 */
class FileActionTaskFailedListener implements IEventListener {

	public function __construct(
		private TaskProcessingService $taskProcessingService,
		private NotificationService $notificationService,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof TaskFailedEvent)) {
			return;
		}

		$task = $event->getTask();
		$customId = $task->getCustomId();
		$appId = $task->getAppId();
		$taskTypeId = $task->getTaskTypeId();

		if ($customId === null || $appId !== (Application::APP_ID . ':file-action')) {
			return;
		}

		if (!$this->taskProcessingService->isFileActionTaskTypeAuthorized($taskTypeId)) {
			return;
		}

		if (preg_match('/^file-action:(\d+)$/', $customId, $matches)) {
			$sourceFileId = (int)$matches[1];
			$this->logger->debug('FileActionTaskListener', ['source file id' => $sourceFileId]);
			$userFolder = $this->rootFolder->getUserFolder($task->getUserId());
			$sourceFile = $userFolder->getFirstNodeById($sourceFileId);
			$this->notificationService->sendFileActionNotification(
				$task->getUserId(), $taskTypeId,
				$sourceFileId, $sourceFile->getName(), $userFolder->getRelativePath($sourceFile->getPath()),
			);
		}
	}
}
