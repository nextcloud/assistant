<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OC\User\NoUserException;
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
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use RuntimeException;

class TaskProcessingService {

	public function __construct(
		private IManager $taskProcessingManager,
		private IRootFolder $rootFolder,
	) {
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

	public function getOutputFileContent(int $fileId): string {
		$file = $this->getOutputFile($fileId);
		return $file->getContent();
	}

	public function isFileActionTaskTypeAuthorized(string $taskTypeId): bool {
		$authorizedTaskTypes = [AudioToText::ID, TextToTextSummary::ID];
		if (class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech')) {
			$authorizedTaskTypes[] = \OCP\TaskProcessing\TaskTypes\TextToSpeech::ID;
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
	 * @throws GenericFileException
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 * @throws NoUserException
	 */
	public function runFileAction(string $userId, int $fileId, string $taskTypeId): int {
		if (!$this->isFileActionTaskTypeAuthorized($taskTypeId)) {
			throw new PreConditionNotMetException();
		}
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$file = $userFolder->getFirstNodeById($fileId);
		if (!$file instanceof File) {
			throw new NotFoundException('File is not a file');
		}
		$input = $taskTypeId === AudioToText::ID
			? ['input' => $fileId]
			: ['input' => $file->getContent()];
		$task = new Task(
			$taskTypeId,
			$input,
			Application::APP_ID,
			$userId,
			'file-action:' . $fileId,
		);
		$this->taskProcessingManager->scheduleTask($task);
		$taskId = $task->getId();
		if ($taskId === null) {
			throw new Exception('The task could not be scheduled');
		}
		return $taskId;
	}
}
