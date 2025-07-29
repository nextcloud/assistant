<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

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
		$taskTypeId = $task->getTaskTypeId();

		if ($customId === null) {
			return;
		}

		if (!$this->taskProcessingService->isFileActionTaskTypeSupported($taskTypeId)) {
			return;
		}

		if (preg_match('/^file-action:(\d+)$/', $customId, $matches)) {
			$sourceFileId = (int)$matches[1];
			$this->logger->debug('FileActionTaskListener', ['source_file_id' => $sourceFileId]);
			$userFolder = $this->rootFolder->getUserFolder($task->getUserId());
			$sourceFile = $userFolder->getFirstNodeById($sourceFileId);
			$this->notificationService->sendFileActionNotification(
				$task->getUserId(), $taskTypeId, $task->getId(),
				$sourceFileId, $sourceFile->getName(), $userFolder->getRelativePath($sourceFile->getPath()),
			);
		}
	}
}
