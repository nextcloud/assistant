<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\IConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\ContextAgentInteraction;
use Psr\Log\LoggerInterface;

class ScheduledTasksService {
	private const SCHEDULED_TASKS_SESSION_TITLE = 'Scheduled Tasks';
	private const CONFIG_KEY = 'scheduled_tasks_session_id';

	public function __construct(
		private SessionMapper $sessionMapper,
		private MessageMapper $messageMapper,
		private IConfig $config,
		private ITaskProcessingManager $taskProcessingManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get or create the scheduled tasks chat session for a user
	 *
	 * @param string $userId
	 * @return Session
	 * @throws \OCP\DB\Exception
	 */
	public function getOrCreateScheduledTasksSession(string $userId): Session {
		// Check if session ID is stored in user config
		$sessionId = $this->config->getUserValue($userId, Application::APP_ID, self::CONFIG_KEY, '');

		if ($sessionId !== '') {
			// Try to get existing session
			try {
				$session = $this->sessionMapper->getUserSession($userId, (int)$sessionId);
				if ($session !== null) {
					return $session;
				}
			} catch (\OCP\AppFramework\Db\DoesNotExistException | \OCP\AppFramework\Db\MultipleObjectsReturnedException $e) {
				// Session doesn't exist, create a new one
			}
		}

		// Create new session
		$session = new Session();
		$session->setUserId($userId);
		$session->setTitle(self::SCHEDULED_TASKS_SESSION_TITLE);
		$session->setTimestamp(time());
		$session->setAgencyConversationToken(null);
		$session->setAgencyPendingActions(null);
		$this->sessionMapper->insert($session);

		// Create initial system message
		$systemMsg = new Message();
		$systemMsg->setSessionId($session->getId());
		$systemMsg->setRole('system');
		$systemMsg->setAttachments('[]');
		$systemMsg->setContent('This is a dedicated chat session for scheduled tasks. Each scheduled event will run an assistant task and store its interaction here.');
		$systemMsg->setTimestamp($session->getTimestamp());
		$systemMsg->setSources('[]');
		$this->messageMapper->insert($systemMsg);

		// Store session ID in user config
		$this->config->setUserValue($userId, Application::APP_ID, self::CONFIG_KEY, (string)$session->getId());

		return $session;
	}

	/**
	 * Execute a scheduled task by creating a ContextAgentInteraction task
	 *
	 * @param string $userId
	 * @param string $prompt The event description to use as the task prompt
	 * @return array The task output
	 * @throws \Exception
	 */
	public function executeScheduledTask(string $userId, string $prompt): array {
		$session = $this->getOrCreateScheduledTasksSession($userId);

		// Add user message
		$userMessage = new Message();
		$userMessage->setSessionId($session->getId());
		$userMessage->setRole('human');
		$userMessage->setContent($prompt);
		$userMessage->setTimestamp(time());
		$userMessage->setSources('[]');
		$userMessage->setAttachments('[]');
		$this->messageMapper->insert($userMessage);

		try {
			// Check if ContextAgentInteraction is available
			$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
			if (!class_exists(ContextAgentInteraction::class) || !isset($availableTaskTypes[ContextAgentInteraction::ID])) {
				throw new \Exception('ContextAgentInteraction task type is not available');
			}

			// Create and run ContextAgentInteraction task
			$task = new Task(
				ContextAgentInteraction::ID,
				[
					'input' => $prompt,
					'confirmation' => 1, // Auto-confirm actions
					'conversation_token' => $session->getAgencyConversationToken() ?? '{}',
				],
				Application::APP_ID . ':scheduled-tasks',
				$userId,
			);

			// Schedule the task synchronously
			$this->taskProcessingManager->scheduleTask($task);

			// Wait for task completion (with timeout)
			$maxWaitTime = 300; // 5 minutes
			$startTime = time();
			while ($task->getStatus() === Task::STATUS_SCHEDULED || $task->getStatus() === Task::STATUS_RUNNING) {
				if (time() - $startTime > $maxWaitTime) {
					throw new \Exception('Task execution timeout');
				}
				sleep(1);
				$task = $this->taskProcessingManager->getTask($task->getId());
			}

			if ($task->getStatus() !== Task::STATUS_SUCCESSFUL) {
				throw new \Exception('Task execution failed');
			}

			$output = $task->getOutput();

			// Update session conversation token if available
			if (isset($output['conversation_token'])) {
				$session->setAgencyConversationToken($output['conversation_token']);
				$this->sessionMapper->update($session);
			}

			// Add assistant response message
			$assistantMessage = new Message();
			$assistantMessage->setSessionId($session->getId());
			$assistantMessage->setRole('assistant');
			$assistantMessage->setContent($output['output'] ?? '');
			$assistantMessage->setTimestamp(time());
			$assistantMessage->setSources(json_encode($output['sources'] ?? []));
			$assistantMessage->setAttachments('[]');
			$assistantMessage->setOcpTaskId($task->getId());
			$this->messageMapper->insert($assistantMessage);

			return $output;
		} catch (\Exception $e) {
			$this->logger->error('Failed to execute scheduled task', [
				'exception' => $e,
				'userId' => $userId,
				'prompt' => $prompt,
			]);

			// Add error message
			$errorMessage = new Message();
			$errorMessage->setSessionId($session->getId());
			$errorMessage->setRole('assistant');
			$errorMessage->setContent('Error executing scheduled task: ' . $e->getMessage());
			$errorMessage->setTimestamp(time());
			$errorMessage->setSources('[]');
			$errorMessage->setAttachments('[]');
			$this->messageMapper->insert($errorMessage);

			throw $e;
		}
	}
}
