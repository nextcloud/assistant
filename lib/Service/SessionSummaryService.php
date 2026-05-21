<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\BackgroundJob\GenerateNewChatSummaries;
use OCA\Assistant\BackgroundJob\RegenerateOutdatedChatSummariesJob;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToText;
use Psr\Log\LoggerInterface;

/**
 * We summarize chat sessions that are toggled to be remembered to inject the summaries as memories into LLM calls
 */
class SessionSummaryService {
	public const BATCH_SIZE = 10;
	public const SUMMARY_MESSAGE_LIMIT = 150;

	public const MAX_INJECTED_SUMMARIES = 10;

	public function __construct(
		private SessionMapper $sessionMapper,
		private MessageMapper $messageMapper,
		private TaskProcessingService $taskProcessingService,
		private LoggerInterface $logger,
		private IAppConfig $appConfig,
		private IJobList $jobList,
	) {
	}

	private function generateSummaries(array $sessions): void {
		foreach ($sessions as $session) {
			try {
				$messages = $this->messageMapper->getMessages($session->getId(), 0, self::SUMMARY_MESSAGE_LIMIT);
				if ($messages[0]->getRole() === 'system') {
					array_shift($messages);
				}

				$prompt = "Summarize insights about the user's circumstances, preferences and choices from the following conversation. Be as concise as possible. Especially mention any facts or numbers stated by the user. Do not add an introductory sentence or any other remarks.\n\n";

				foreach ($messages as $message) {
					$prompt .= $message->getRole() . ': ' . $message->getContent() . "\n\n";
				}

				$task = new Task(TextToText::ID, [
					'input' => $prompt,
				], 'assistant:summary-service', $session->getUserId());
				$output = $this->taskProcessingService->runTaskProcessingTask($task);
				$session->setSummary($output['output']);
				$session->setIsSummaryUpToDate(true);
				$this->sessionMapper->update($session);
			} catch (\Throwable $e) {
				$this->logger->warning('Failed to generate summary for chat session ' . $session->getId(), ['exception' => $e]);
			}
		}
	}

	public function regenerateSummariesForOutdatedSessions(string $userId): void {
		try {
			$sessions = $this->sessionMapper->getRememberedUserSessionsWithOutdatedSummaries($userId, self::BATCH_SIZE);
			$this->generateSummaries($sessions);
		} catch (Exception $e) {
			$this->logger->warning('Failed to generate chat summaries for outdated sessions', ['exception' => $e]);
		}
	}

	public function generateSummariesForNewSessions(string $userId): void {
		try {
			$sessions = $this->sessionMapper->getRememberedUserSessionsWithoutSummaries($userId, self::BATCH_SIZE);
			$this->generateSummaries($sessions);
		} catch (Exception $e) {
			$this->logger->warning('Failed to generate chat summaries for new sessions', ['exception' => $e]);
		}
	}

	public function scheduleJobsForUser(string $userId) {
		if (!$this->jobList->has(GenerateNewChatSummaries::class, ['userId' => $userId])) {
			$this->jobList->add(GenerateNewChatSummaries::class, ['userId' => $userId]);
		}
		if (!$this->jobList->has(RegenerateOutdatedChatSummariesJob::class, ['userId' => $userId])) {
			$this->jobList->add(RegenerateOutdatedChatSummariesJob::class, ['userId' => $userId]);
		}
	}

	/**
	 * @return array<string>
	 */
	public function getUserSessionSummaries(?string $userId): array {
		try {
			$sessions = $this->sessionMapper->getRememberedUserSessions($userId, self::MAX_INJECTED_SUMMARIES);
			return array_filter(array_map(fn (Session $session) => $session->getSummary(), $sessions), fn ($summary) => $summary !== null);
		} catch (Exception $e) {
			$this->logger->error('Failed to get remembered user sessions', ['exception' => $e]);
			return [];
		}
	}

	/**
	 * returns all remembered chats as summaries where available and whole chats where not
	 * @return list<string>
	 */
	public function getMemories(?string $userId): array {
		$memories = [];
		try {
			$sessions = $this->sessionMapper->getRememberedUserSessions($userId, self::MAX_INJECTED_SUMMARIES);
			foreach ($sessions as $session) {
				if ($session->getSummary() !== null) {
					$memory = $session->getSummary() ?? '';
					if (!$session->getIsSummaryUpToDate()) {
						$lastNMessages = intval($this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10', lazy: true));
						$chatHistory = $this->messageMapper->getMessages($session->getId(), 0, $lastNMessages);
						$memory .= 'The summary is outdated. These are the last messages in the raw chat history: ' . json_encode($chatHistory);
					}

				} else {
					$lastNMessages = intval($this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10', lazy: true));
					$chatHistory = $this->messageMapper->getMessages($session->getId(), 0, $lastNMessages);
					$memory = 'This is the raw chat history of a chat between the user and Assistant: ' . json_encode($chatHistory);
				}
				$memories[] = $memory;
			}
			return $memories;
		} catch (Exception $e) {
			$this->logger->error('Failed to get remembered user sessions', ['exception' => $e]);
			return [];
		}
	}



}
