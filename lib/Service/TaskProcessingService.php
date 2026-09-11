<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use OCP\TaskProcessing\Exception\Exception;
use OCP\TaskProcessing\Exception\NotFoundException;
use OCP\TaskProcessing\Exception\PreConditionNotMetException;
use OCP\TaskProcessing\Exception\UnauthorizedException;
use OCP\TaskProcessing\Exception\ValidationException;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\IProvider;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use Psr\Log\LoggerInterface;
use RuntimeException;

class TaskProcessingService {

	public function __construct(
		private IManager $taskProcessingManager,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
		private AssistantService $assistantService,
		private AttachmentService $attachmentService,
	) {
	}

	public function getPreferredProvider(string $taskTypeId): IProvider {
		return $this->taskProcessingManager->getPreferredProvider($taskTypeId);
	}

	/**
	 * @param Task $task
	 * @return array
	 * @throws Exception
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 */
	public function runTaskProcessingTask(Task $task): array {
		$task = $this->taskProcessingManager->runTask($task);
		$taskOutput = $task->getOutput();
		if ($taskOutput === null) {
			throw new RuntimeException('Task with id ' . $task->getId() . ' does not have any output');
		}
		return $taskOutput;
	}

	/**
	 * @param int $fileId
	 * @return File
	 * @throws NotFoundException
	 */
	public function getOutputFile(int $fileId): File {
		$node = $this->rootFolder->getFirstNodeById($fileId);
		if ($node === null) {
			$node = $this->rootFolder->getFirstNodeByIdInPath($fileId, '/' . $this->rootFolder->getAppDataDirectoryName() . '/');
			if (!$node instanceof File) {
				throw new NotFoundException('Node is not a file');
			}
		} elseif (!$node instanceof File) {
			throw new NotFoundException('Node is not a file');
		}
		return $node;
	}

	/**
	 * @param int $fileId
	 * @return string
	 * @throws GenericFileException
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getOutputFileContent(int $fileId): string {
		$file = $this->getOutputFile($fileId);
		return $file->getContent();
	}

	/**
	 * Task types that take the input file itself rather than its text content.
	 */
	private function isAudioInputTaskType(string $taskTypeId): bool {
		return $taskTypeId === AudioToText::ID
			|| (class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToTextSubtitles')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\AudioToTextSubtitles::ID);
	}

	public function isFileActionTaskTypeSupported(string $taskTypeId): bool {
		$authorizedTaskTypes = [AudioToText::ID, TextToTextSummary::ID];
		if (class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech')) {
			$authorizedTaskTypes[] = \OCP\TaskProcessing\TaskTypes\TextToSpeech::ID;
		}
		if (class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToTextSubtitles')) {
			$authorizedTaskTypes[] = \OCP\TaskProcessing\TaskTypes\AudioToTextSubtitles::ID;
		}
		return in_array($taskTypeId, $authorizedTaskTypes, true);
	}

	/**
	 * Execute a file action
	 *
	 * @param string $userId
	 * @param int $fileId
	 * @param string $taskTypeId
	 * @return int The scheduled task ID
	 * @throws Exception
	 * @throws NotFoundException
	 */
	public function runFileAction(string $userId, int $fileId, string $taskTypeId): int {
		if (!$this->isFileActionTaskTypeSupported($taskTypeId)) {
			throw new Exception('Invalid task type for file action');
		}
		try {
			$input = $this->buildFileActionInput($userId, $fileId, $taskTypeId);
		} catch (NotPermittedException|GenericFileException|LockedException|\OCP\Files\NotFoundException|Exception $e) {
			$this->logger->warning('Assistant runFileAction, impossible to read the file action input file', ['exception' => $e]);
			throw new Exception('Impossible to read the file action input file');
		}
		$task = new Task(
			$taskTypeId,
			$input,
			Application::APP_ID,
			$userId,
			'file-action:' . $fileId,
		);
		try {
			$this->taskProcessingManager->scheduleTask($task);
		} catch (PreConditionNotMetException|ValidationException|Exception|UnauthorizedException $e) {
			$this->logger->warning('Assistant runFileAction, impossible to schedule the task', ['exception' => $e]);
			throw new Exception('Impossible to schedule the task');
		}
		$taskId = $task->getId();
		if ($taskId === null) {
			throw new Exception('The task could not be scheduled');
		}
		return $taskId;
	}

	/**
	 * Build the task input for a file action.
	 *
	 * Audio task types consume the file directly. For the others we normally
	 * extract the text ourselves, but when the provider can take files and the
	 * file is one our parsers handle badly, we hand it over untouched instead.
	 *
	 * The mandatory text input stays empty in that case: the provider builds
	 * its own instructions around the attachment, and anything we put there
	 * would be untranslated and fight that prompt.
	 *
	 * @return array<string, mixed>
	 * @throws GenericFileException
	 * @throws LockedException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function buildFileActionInput(string $userId, int $fileId, string $taskTypeId): array {
		if ($this->isAudioInputTaskType($taskTypeId)) {
			return ['input' => $fileId];
		}

		$file = $this->rootFolder->getUserFolder($userId)->getFirstNodeById($fileId);
		if ($file instanceof File && $this->attachmentService->shouldAttachFile($file, $taskTypeId)) {
			$this->logger->debug('Assistant file action: sending the file to the provider as an attachment', [
				'fileId' => $fileId,
				'taskTypeId' => $taskTypeId,
			]);
			return ['input' => '', 'input_attachments' => [$fileId]];
		}

		return ['input' => $this->assistantService->parseTextFromFile($userId, fileId: $fileId)];
	}
}
