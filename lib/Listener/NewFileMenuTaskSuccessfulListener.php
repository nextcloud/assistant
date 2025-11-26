<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\Service\AssistantService;
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\SystemTagService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\SystemTag\TagNotFoundException;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class NewFileMenuTaskSuccessfulListener implements IEventListener {

	public function __construct(
		private NotificationService $notificationService,
		private AssistantService $assistantService,
		private LoggerInterface $logger,
		private IRootFolder $rootFolder,
		private SystemTagService  $systemTagService,
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
		$isNewImageFileAction = preg_match($customIdPattern, $task->getCustomId(), $matches) === 1;

		// For tasks with customId "new-image-file:<directoryIdNumber>" we always send a notification
		if (!$isNewImageFileAction) {
			return;
		}

		$directoryId = (int)$matches[1];
		$fileId = (int)$task->getOutput()['images'][0];
		try {
			$targetFile = $this->assistantService->saveNewFileMenuActionFile($task->getUserId(), $task->getId(), $fileId, $directoryId);
			try {
				$this->systemTagService->assignAiTagToFile((string)$targetFile->getId());
			} catch (TagNotFoundException $e) {
				$this->logger->warning('NewFileMenuTaskListener could not write AI tag to file', ['target' => $targetFile->getName(), 'exception' => $e]);
			}
			$userFolder = $this->rootFolder->getUserFolder($task->getUserId());
			$directory = $targetFile->getParent();
			$this->notificationService->sendNewImageFileNotification(
				$task->getUserId(), $task->getId(),
				$directoryId, $directory->getName(), $userFolder->getRelativePath($directory->getPath()),
				$targetFile->getId(), $targetFile->getName(), $userFolder->getRelativePath($targetFile->getPath()),
			);
		} catch (\Exception $e) {
			$this->logger->error('NewFileMenuTaskListener: Failed to save new file menu action file.', [
				'task' => $task->jsonSerialize(),
				'exception' => $e,
			]);
		}

	}
}
