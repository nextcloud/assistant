<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Service\FreePrompt;

use Exception;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\FreePrompt\PromptMapper;
use OCA\TpAssistant\Db\MetaTaskMapper;
use OCP\AppFramework\Http;
use OCP\DB\Exception as DBException;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use OCP\TextProcessing\Exception\TaskFailureException;
use OCP\TextProcessing\FreePromptTaskType;
use OCP\TextProcessing\IManager;
use OCP\TextProcessing\Task;
use Psr\Log\LoggerInterface;

class FreePromptService {
	public function __construct(
		private LoggerInterface $logger,
		private IManager        $textProcessingManager,
		private PromptMapper    $promptMapper,
		private IL10N           $l10n,
		private MetaTaskMapper  $metaTaskMapper,
	) {
	}

	/*
	 * @param string $prompt
	 * @param string $userId
	 * @return string
	 * @throws Exception
	 */
	public function processPrompt(string $prompt, string $userId): string {
		$taskTypes = $this->textProcessingManager->getAvailableTaskTypes();
		if (!in_array(FreePromptTaskType::class, $taskTypes)) {
			$this->logger->warning('FreePromptTaskType not available');
			throw new Exception($this->l10n->t('FreePromptTaskType not available'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Generate a unique id for this generation
		while (true) {
			$genId = bin2hex(random_bytes(32));
			// Exceedingly unlikely that this will ever happen, but just in case:
			if(count($this->textProcessingManager->getUserTasksByApp($userId, Application::APP_ID, $genId)) === 0) {
				break;
			} else {
				continue;
			}
		}

		$promptTask = new Task(FreePromptTaskType::class, $prompt, Application::APP_ID, $userId, $genId);

		// Run or schedule the task:
		try {
			$this->textProcessingManager->runOrScheduleTask($promptTask);
		} catch (DBException | PreConditionNotMetException | TaskFailureException $e) {
			$this->logger->warning('Failed to run or schedule a task', ['exception' => $e]);
			throw new Exception($this->l10n->t('Failed to run or schedule a task'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Create an assistant task for the free prompt task:
		$this->metaTaskMapper->createMetaTask(
			$userId,
			['prompt' => $prompt],
			$promptTask->getOutput(),
			time(),
			$promptTask->getId(),
			FreePromptTaskType::class,
			Application::APP_ID,
			$promptTask->getStatus(),
			Application::TASK_CATEGORY_TEXT_GEN,
			$genId
		);

		// If the task was run immediately, we'll skip the notification..
		// Otherwise we would have to dispatch the notification here.

		// Save prompt to database
		$this->promptMapper->createPrompt($userId, $prompt);

		return $genId;
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws Exception
	 */
	public function getPromptHistory(string $userId): array {
		try {
			return $this->promptMapper->getPromptsOfUser($userId);
		} catch (DBException $e) {
			$this->logger->warning('Failed to get prompts of user', ['exception' => $e]);
			throw new Exception($this->l10n->t('Failed to get prompt history'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param string $genId
	 * @param string $userId
	 * @return array
	 * @throws Exception
	 */
	public function getOutputs(string $genId, string $userId): array {
		$tasks = $this->textProcessingManager->getUserTasksByApp($userId, Application::APP_ID, $genId);

		if (count($tasks) === 0) {
			$this->logger->warning('No tasks found for gen id: ' . $genId);
			throw new Exception($this->l10n->t('Generation not found'), Http::STATUS_BAD_REQUEST);
		}

		$outputs = [];
		/** @var Task $task */
		foreach ($tasks as $task) {
			$row = ['prompt' => $task->getInput(), 'text' => $task->getOutput(), 'status' => $task->getStatus()];
			if ($task->getStatus() === Task::STATUS_SCHEDULED) {
				$row['completion_time'] = $task->getCompletionExpectedAt();
			}
			array_push($outputs, $row);
		}

		return $outputs;
	}

	/**
	 * @param string $genId
	 * @param string $userId
	 * @return void
	 * @throws Exception
	 */
	public function cancelGeneration(string $genId, string $userId): void {
		// Get all tasks that have this genId as identifier.
		/** @var Task[] $tasks */
		$tasks = $this->textProcessingManager->getUserTasksByApp($userId, Application::APP_ID, $genId);

		// Cancel all tasks
		foreach ($tasks as $task) {
			$this->textProcessingManager->deleteTask($task);
		}

		return;
	}

}
