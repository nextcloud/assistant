<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
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
class FileActionTaskListener implements IEventListener {

	public function __construct(
		private TaskProcessingService $taskProcessingService,
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
		$appId = $task->getAppId();
		$taskTypeId = $task->getTaskTypeId();

		if ($customId === null || $appId !== (Application::APP_ID . ':file-action')) {
			return;
		}

		if (!$this->taskProcessingService->isFileActionTaskTypeAuthorized($taskTypeId)) {
			return;
		}

		if (preg_match('/^file-action:(\d+)$/', $customId, $matches)) {
			// we get the task output, write it in the output file (in the same dir as the source one)
			$sourceFileId = (int)$matches[1];
			$this->logger->debug('FileActionTaskListener', ['source file id' => $sourceFileId]);
			$userFolder = $this->rootFolder->getUserFolder($task->getUserId());
			$sourceFile = $userFolder->getFirstNodeById($sourceFileId);
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
				$targetFileName = $sourceFile->getName() . '_tts.' . $extension;
				$sourceFileParent->newFile($targetFileName, $speechFile->fopen('rb'));
			} else {
				$textResult = $task->getOutput()['output'];
				$suffix = $taskTypeId === TextToTextSummary::ID ? 'summarized' : 'transcribed';
				$targetFileName = $sourceFile->getName() . '_' . $suffix . '.txt';
				$sourceFileParent->newFile($targetFileName, $textResult);
				$this->logger->debug('FileActionTaskListener wrote file', ['target' => $targetFileName]);
			}
			// TODO maybe send a notification
		}
	}
}
