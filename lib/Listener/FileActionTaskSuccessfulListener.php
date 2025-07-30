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
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<TaskSuccessfulEvent>
 */
class FileActionTaskSuccessfulListener implements IEventListener {

	public function __construct(
		private TaskProcessingService $taskProcessingService,
		private NotificationService $notificationService,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof TaskSuccessfulEvent)) {
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
			// we get the task output, write it in the output file (in the same dir as the source one)
			$sourceFileId = (int)$matches[1];
			$userFolder = $this->rootFolder->getUserFolder($task->getUserId());
			$sourceFile = $userFolder->getFirstNodeById($sourceFileId);
			$this->logger->debug('FileActionTaskListener', ['source file id' => $sourceFileId]);
			try {
				$sourceFileParent = $sourceFile->getParent();
				$this->logger->debug('FileActionTaskListener', ['source file PARENT id' => $sourceFileParent->getId()]);
				if (
					class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech')
					&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\TextToSpeech::ID
				) {
					$speechFileId = (int)$task->getOutput()['speech'];
					$speechFile = $this->taskProcessingService->getOutputFile($speechFileId);
					$mimeType = mime_content_type($speechFile->fopen('rb'));
					$mimeType = $mimeType ?: 'audio/wav';
					$mimes = new \Mimey\MimeTypes;
					$extension = $mimes->getExtension($mimeType);
					if ($extension === 'mpga') {
						$extension = 'mp3';
					}
					$targetFileName = $sourceFile->getName() . ' - text to speech.' . $extension;
					$targetFile = $sourceFileParent->newFile($targetFileName, $speechFile->fopen('rb'));
				} else {
					$textResult = $task->getOutput()['output'];
					$suffix = $taskTypeId === TextToTextSummary::ID ? 'summarized' : 'transcribed';
					$targetFileName = $sourceFile->getName() . ' - ' . $suffix . '.md';
					$targetFile = $sourceFileParent->newFile($targetFileName, $textResult);
					$this->logger->debug('FileActionTaskListener wrote file', ['target' => $targetFileName]);
				}
				$this->notificationService->sendFileActionNotification(
					$task->getUserId(), $taskTypeId, $task->getId(),
					$sourceFileId, $sourceFile->getName(), $userFolder->getRelativePath($sourceFile->getPath()),
					$targetFile->getId(), $targetFile->getName(), $userFolder->getRelativePath($targetFile->getPath()),
				);
			} catch (\Exception|\Throwable $e) {
				$this->logger->error('FileActionTaskListener task succeeded but listener failed to write the result file', [
					'source file id' => $sourceFileId,
					'exception' => $e,
				]);
				$this->notificationService->sendFileActionNotification(
					$task->getUserId(), $taskTypeId, $task->getId(),
					$sourceFileId, $sourceFile->getName(), $userFolder->getRelativePath($sourceFile->getPath()),
				);
			}
		}
	}
}
