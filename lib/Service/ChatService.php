<?php

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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\TaskProcessing\Exception\PreConditionNotMetException;
use OCP\TaskProcessing\Exception\ValidationException;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use Psr\Log\LoggerInterface;

class ChatService {
	public const EMPTY_CONVERSATION_TOKEN = '{}';

	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IAppConfig $appConfig,
		private readonly IL10N $l10n,
		private readonly SessionMapper $sessionMapper,
		private readonly MessageMapper $messageMapper,
		private readonly SessionSummaryService $sessionSummaryService,
		private readonly IManager $taskProcessingManager,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @throws InternalException
	 * @throws UnauthorizedException
	 */
	public function createChatSession(?string $userId, ?int $timestamp = null, ?string $title = null): Session {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new UnauthorizedException($this->l10n->t('User not found'));
		}

		if ($timestamp > 10_000_000_000) {
			$timestamp = intdiv($timestamp, 1000);
		}

		$userInstructions = $this->appConfig->getValueString(
			Application::APP_ID,
			'chat_user_instructions',
			Application::CHAT_USER_INSTRUCTIONS,
			lazy: true,
		) ?: Application::CHAT_USER_INSTRUCTIONS;
		$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);

		$session = new Session();
		$session->setUserId($userId);
		$session->setTitle($title);
		$session->setTimestamp($timestamp);
		$session->setAgencyConversationToken(null);
		$session->setAgencyPendingActions(null);
		try {
			$this->sessionMapper->insert($session);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}

		$systemMsg = new Message();
		$systemMsg->setSessionId($session->getId());
		$systemMsg->setRole(Message::ROLE_SYSTEM);
		$systemMsg->setAttachments('[]');
		$systemMsg->setContent($userInstructions);
		$systemMsg->setTimestamp($session->getTimestamp());
		$systemMsg->setSources('[]');
		try {
			$this->messageMapper->insert($systemMsg);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}

		return $session;
	}

	/**
	 * @throws InternalException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function updateSession(?string $userId, int $sessionId, ?string $title = null, ?bool $isRemembered = null): Session {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}
		try {
			$session = $this->sessionMapper->getUserSession($userId, $sessionId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException($this->l10n->t('Session not found'), previous: $e);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			throw new InternalException(previous: $e);
		}
		if ($title === null && $isRemembered === null) {
			return $session;
		}
		if ($title !== null) {
			$session->setTitle($title);
		}
		if ($isRemembered !== null) {
			$session->setIsRemembered($isRemembered);
			// schedule summarizer jobs for this chat user
			if ($isRemembered) {
				$this->sessionSummaryService->scheduleJobsForUser($userId);
			}
		}
		try {
			$this->sessionMapper->update($session);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		return $session;
	}

	/**
	 * @throws InternalException
	 * @throws UnauthorizedException
	 */
	public function deleteSession(?string $userId, int $sessionId): void {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}

		try {
			$this->sessionMapper->deleteSession($userId, $sessionId);
			$this->messageMapper->deleteMessagesBySession($sessionId);
		} catch (Exception|\RuntimeException $e) {
			throw new InternalException(previous: $e);
		}
	}

	/**
	 * @return list<Session>
	 * @throws InternalException
	 * @throws UnauthorizedException
	 */
	public function getSessionsForUser(?string $userId): array {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}
		try {
			return $this->sessionMapper->getUserSessions($userId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
	}

	/**
	 * @throws BadRequestException
	 * @throws InternalException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function createMessage(?string $userId, int $sessionId, string $role, string $content, int $timestamp, ?array $attachments = null, bool $firstHumanMessage = false): Message {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}

		if (strlen($content) > Application::MAX_TEXT_INPUT_LENGTH) {
			throw new BadRequestException($this->l10n->t('The new message is too long'));
		}

		if ($timestamp > 10_000_000_000) {
			$timestamp = intdiv($timestamp, 1000);
		}

		// refuse empty text content if context agent is not available (we do classic chat) AND there is no attachment
		// in other words: accept empty content if we are using agency OR there are attachments
		$content = trim($content);
		if (empty($content)
			&& !$this->isContextAgentAvailable()
			&& $attachments === null
		) {
			throw new BadRequestException($this->l10n->t('Message content is empty'));
		}

		try {
			$sessionExists = $this->sessionMapper->exists($userId, $sessionId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$sessionExists) {
			throw new NotFoundException($this->l10n->t('Session not found'));
		}

		$message = new Message();
		$message->setSessionId($sessionId);
		$message->setRole($role);
		$message->setContent($content);
		$message->setTimestamp($timestamp);
		$message->setSources('[]');
		$message->setAttachments('[]');
		if ($attachments !== null) {
			try {
				$encodedAttachments = json_encode($attachments, JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				throw new BadRequestException($this->l10n->t('Failed to encode attachments'));
			}
			if ($encodedAttachments !== false) {
				$message->setAttachments($encodedAttachments);
			}
		}
		try {
			$this->messageMapper->insert($message);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if ($firstHumanMessage) {
			// set the title of the session based on first human message
			try {
				$this->sessionMapper->updateSessionTitle(
					$userId,
					$sessionId,
					strlen($content) > 140 ? mb_substr($content, 0, 140) . '...' : $content,
				);
			} catch (Exception $e) {
				$this->logger->error('Failed to update session title', ['exception' => $e]);
				// pass as the main operation succeeded
			}
		}
		return $message;
	}

	/**
	 * @return list<Message>
	 * @throws InternalException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function getSessionMessages(?string $userId, int $sessionId, $limit = 20, int $cursor = 0): array {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}

		try {
			$sessionExists = $this->sessionMapper->exists($userId, $sessionId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$sessionExists) {
			throw new NotFoundException($this->l10n->t('Session not found'));
		}

		/** @var list<Message> $messages */
		try {
			$messages = $this->messageMapper->getMessages($sessionId, $cursor, $limit);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!empty($messages) && $messages[0]->getRole() === Message::ROLE_SYSTEM) {
			array_shift($messages);
		}

		return $messages;
	}

	/**
	 * @throws InternalException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function getSessionMessage(?string $userId, int $sessionId, int $messageId): Message {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}

		try {
			$sessionExists = $this->sessionMapper->exists($userId, $sessionId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$sessionExists) {
			throw new NotFoundException($this->l10n->t('Session not found'));
		}
		try {
			return $this->messageMapper->getMessageById($sessionId, $messageId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException($this->l10n->t('Message not found'), previous: $e);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			throw new InternalException(previous: $e);
		}
	}

	/**
	 * @throws InternalException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function deleteSessionMessage(?string $userId, int $sessionId, int $messageId): void {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}
		try {
			$sessionExists = $this->sessionMapper->exists($userId, $sessionId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$sessionExists) {
			throw new NotFoundException($this->l10n->t('Session not found'));
		}

		try {
			$this->messageMapper->deleteMessageById($sessionId, $messageId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
	}

	/**
	 * @throws InternalException
	 * @throws BadRequestException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function scheduleMessageGeneration(?string $userId, int $sessionId, int $agencyConfirm = 0): int {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}
		try {
			$sessionExists = $this->sessionMapper->exists($userId, $sessionId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$sessionExists) {
			throw new NotFoundException($this->l10n->t('Session not found'));
		}

		if ($this->isContextAgentAvailable()) {
			try {
				$lastUserMessage = $this->messageMapper->getLastHumanMessage($sessionId);
			} catch (DoesNotExistException $e) {
				throw new NotFoundException($this->l10n->t('No user message found in this session'), previous: $e);
			} catch (MultipleObjectsReturnedException|Exception $e) {
				throw new InternalException(previous: $e);
			}


			try {
				$session = $this->sessionMapper->getUserSession($userId, $sessionId);
			} catch (DoesNotExistException $e) {
				throw new NotFoundException($this->l10n->t('Session not found'), previous: $e);
			} catch (MultipleObjectsReturnedException|Exception $e) {
				throw new InternalException(previous: $e);
			}
			$lastConversationToken = $session->getAgencyConversationToken() ?? self::EMPTY_CONVERSATION_TOKEN;

			$lastAttachments = $lastUserMessage->jsonSerialize()['attachments'];
			$audioAttachment = $lastAttachments[0] ?? null;
			$isContextAgentAudioAvailable = $this->isContextAgentAudioAvailable();
			if ($audioAttachment !== null
				&& isset($audioAttachment['type'])
				&& $audioAttachment['type'] === 'Audio'
				&& $isContextAgentAudioAvailable
			) {
				// audio agency
				$fileId = $audioAttachment['file_id'];
				$taskId = $this->scheduleAgencyAudioTask($userId, $fileId, $agencyConfirm, $lastConversationToken, $sessionId, $lastUserMessage->getId());
			} else {
				// classic agency
				$prompt = $lastUserMessage->getContent();
				$taskId = $this->scheduleAgencyTask($userId, $prompt, $agencyConfirm, $lastConversationToken, $sessionId);
			}
		} else {
			// classic chat
			$systemPrompt = '';
			try {
				$firstMessage = $this->messageMapper->getFirstNMessages($sessionId, 1);
			} catch (DoesNotExistException $e) {
				throw new NotFoundException($this->l10n->t('No message found in this session'), previous: $e);
			} catch (MultipleObjectsReturnedException|Exception $e) {
				throw new InternalException(previous: $e);
			}
			if ($firstMessage->getRole() === Message::ROLE_SYSTEM) {
				$systemPrompt = $firstMessage->getContent();
			}
			try {
				$history = $this->getRawLastMessages($sessionId);
			} catch (Exception|AppConfigTypeConflictException $e) {
				throw new InternalException(previous: $e);
			}
			$lastUserMessage = null;
			while ($history !== []) {
				$lastUserMessage = array_pop($history);
				if ($lastUserMessage !== null && $lastUserMessage->getRole() === Message::ROLE_HUMAN) {
					break;
				}
			}
			if (!$lastUserMessage instanceof Message || $lastUserMessage->getRole() !== Message::ROLE_HUMAN) {
				throw new NotFoundException($this->l10n->t('No human message found in this session'));
			}

			$lastAttachments = $lastUserMessage->jsonSerialize()['attachments'];
			$audioAttachment = $lastAttachments[0] ?? null;
			$isAudioToAudioAvailable = $this->isContextAgentAudioAvailable();
			if ($audioAttachment !== null
				&& isset($audioAttachment['type'])
				&& $audioAttachment['type'] === 'Audio'
				&& $isAudioToAudioAvailable
			) {
				// for an audio chat task, let's try to get the remote audio IDs for all the previous audio messages
				$history = $this->getAudioHistory($history);
				$fileId = $audioAttachment['file_id'];
				$taskId = $this->scheduleAudioChatTask($userId, $fileId, $systemPrompt, $history, $sessionId, $lastUserMessage->getId());
			} else {
				// for a text chat task, let's only use text in the history
				$history = array_map(static function (Message $message) {
					return json_encode([
						'role' => $message->getRole(),
						'content' => $message->getContent(),
					]);
				}, $history);
				$taskId = $this->scheduleLLMChatTask($userId, $lastUserMessage->getContent(), $systemPrompt, $history, $sessionId);
			}
		}
		return $taskId;
	}

	/**
	 * @throws InternalException
	 * @throws BadRequestException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function scheduleAssignmentMessageGeneration(?string $userId, int $sessionId): int {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}
		try {
			$sessionExists = $this->sessionMapper->exists($userId, $sessionId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$sessionExists) {
			throw new NotFoundException($this->l10n->t('Session not found'));
		}

		if (!$this->isContextAgentAvailable()) {
			throw new BadRequestException('context_agent_not_available');
		}
		try {
			$lastUserMessage = $this->messageMapper->getLastHumanMessage($sessionId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException($this->l10n->t('No user message found in this session'), previous: $e);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			throw new InternalException(previous: $e);
		}


		try {
			$session = $this->sessionMapper->getUserSession($userId, $sessionId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException($this->l10n->t('Session not found'), previous: $e);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			throw new InternalException(previous: $e);
		}
		// We reset the context for each interaction, because this is an assignment,
		// the assistant does not remember things between assignment runs
		$lastConversationToken = self::EMPTY_CONVERSATION_TOKEN;

		// classic agency
		$prompt = $lastUserMessage->getContent();
		$taskId = $this->scheduleAgencyTask($userId, $prompt, 0, $lastConversationToken, $sessionId);
		return $taskId;
	}

	/**
	 * @throws BadRequestException
	 * @throws InternalException
	 * @throws NotFoundException
	 * @throws UnauthorizedException|\JsonException
	 */
	public function scheduleTitleGeneration(?string $userId, int $sessionId): int {
		if ($userId === null) {
			throw new UnauthorizedException($this->l10n->t('Unauthorized'));
		}
		try {
			$sessionExists = $this->sessionMapper->exists($userId, $sessionId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$sessionExists) {
			throw new NotFoundException($this->l10n->t('Session not found'));
		}

		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new InternalException($this->l10n->t('User not found'));
		}

		$userInstructions = $this->appConfig->getValueString(
			Application::APP_ID,
			'chat_user_instructions_title',
			Application::CHAT_USER_INSTRUCTIONS_TITLE,
			lazy: true,
		) ?: Application::CHAT_USER_INSTRUCTIONS_TITLE;
		$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);

		try {
			$history = $this->getRawLastMessages($sessionId);
		} catch (Exception|AppConfigTypeConflictException $e) {
			throw new InternalException(previous: $e);
		}
		// history is a list of JSON strings
		$history = array_map(static function (Message $message) {
			return json_encode([
				'role' => $message->getRole(),
				'content' => $message->getContent(),
			], JSON_THROW_ON_ERROR);
		}, $history);
		return $this->scheduleLLMChatTask($userId, $userInstructions, $userInstructions, $history, $sessionId, false);
	}

	public function isContextAgentAvailable(): bool {
		if (!class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentInteraction')) {
			return false;
		}
		return in_array(\OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID, $this->taskProcessingManager->getAvailableTaskTypeIds());
	}

	public function isContextAgentAudioAvailable(): bool {
		if (!class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentAudioInteraction')) {
			return false;
		}
		return in_array(\OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID, $this->taskProcessingManager->getAvailableTaskTypeIds());
	}


	private function getAudioHistory(array $history): array {
		// history is a list of JSON strings
		// The content is the remote audio ID (or the transcription as fallback)
		// We only use the audio ID for assistant messages, if we have one and if it's not expired
		// The audio ID is found in integration_openai's AudioToAudioChat response for example
		// It is an optional output of AudioToAudioChat tasks
		return array_map(static function (Message $message) {
			$entry = [
				'role' => $message->getRole(),
			];
			$attachments = $message->jsonSerialize()['attachments'];
			if ($message->getRole() === Message::ROLE_ASSISTANT
				&& count($attachments) > 0
				&& $attachments[0]['type'] === 'Audio'
				&& isset($attachments[0]['remote_audio_id'])
			) {
				if (!isset($attachments[0]['remote_audio_expires_at'])
					|| time() < $attachments[0]['remote_audio_expires_at']
				) {
					$entry['audio'] = ['id' => $attachments[0]['remote_audio_id']];
					return json_encode($entry);
				}
			}

			$entry['content'] = $message->getContent();
			return json_encode($entry);
		}, $history);
	}

	/**
	 * Get the last N messages (assistant and user messages, avoid initial system prompt) as an array
	 *
	 * @param integer $sessionId
	 * @return array<Message>
	 * @throws AppConfigTypeConflictException
	 * @throws \OCP\DB\Exception
	 */
	private function getRawLastMessages(int $sessionId): array {
		$lastNMessages = (int)$this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10', lazy: true);
		$messages = $this->messageMapper->getMessages($sessionId, 0, $lastNMessages);

		if (!empty($messages) && $messages[0]->getRole() === Message::ROLE_SYSTEM) {
			array_shift($messages);
		}
		return $messages;
	}

	/**
	 * @param string|null $userId
	 * @param string $customId
	 * @return void
	 * @throws BadRequestException
	 * @throws InternalException
	 */
	private function checkIfSessionIsThinking(?string $userId, string $customId): void {
		try {
			$tasks = $this->taskProcessingManager->getUserTasksByApp($userId, Application::APP_ID . ':chatty-llm', $customId);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			throw new BadRequestException('task_query_failed', previous: $e);
		} catch (\JsonException $e) {
			throw new InternalException(previous: $e);
		}
		$tasks = array_filter($tasks, static function (Task $task) {
			return $task->getStatus() === Task::STATUS_RUNNING || $task->getStatus() === Task::STATUS_SCHEDULED;
		});
		// prevent scheduling multiple llm tasks simultaneously for one session
		if (!empty($tasks)) {
			throw new BadRequestException('session_already_thinking');
		}
	}

	/**
	 * Schedule a Chat task
	 *
	 * @throws BadRequestException
	 * @throws InternalException
	 */
	private function scheduleLLMChatTask(
		?string $userId,
		string $newPrompt,
		string $systemPrompt,
		array $history,
		int $sessionId,
		bool $isMessage = true,
	): int {
		$customId = ($isMessage
				? 'chatty-llm:'
				: 'chatty-title:') . $sessionId;
		$this->checkIfSessionIsThinking($userId, $customId);
		$input = [
			'input' => $newPrompt,
			'system_prompt' => $systemPrompt,
			'history' => $history,
		];
		if (isset($this->taskProcessingManager->getAvailableTaskTypes()[TextToTextChat::ID]['optionalInputShape']['memories'])) {
			$input['memories'] = $this->sessionSummaryService->getMemories($userId);
		}
		$task = new Task(TextToTextChat::ID, $input, Application::APP_ID . ':chatty-llm', $userId, $customId);
		try {
			$this->taskProcessingManager->scheduleTask($task);
		} catch (PreConditionNotMetException $e) {
			throw new BadRequestException('pre_condition_not_met', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\UnauthorizedException $e) {
			throw new BadRequestException('unauthorized', previous: $e);
		} catch (ValidationException $e) {
			throw new BadRequestException('validation_failed', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalException(previous: $e);
		}
		return $task->getId() ?? 0;
	}

	/**
	 * Schedule an agency chat task
	 *
	 * @throws BadRequestException
	 * @throws InternalException
	 */
	private function scheduleAgencyTask(
		?string $userId,
		string $content,
		int $confirmation,
		string $conversationToken,
		int $sessionId,
	): int {
		$customId = 'chatty-llm:' . $sessionId;
		$this->checkIfSessionIsThinking($userId, $customId);
		$taskInput = [
			'input' => $content,
			'confirmation' => $confirmation,
			'conversation_token' => $conversationToken,
		];
		/** @psalm-suppress UndefinedClass */
		if (isset($this->taskProcessingManager->getAvailableTaskTypes()[\OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID]['optionalInputShape']['memories'])) {
			$taskInput['memories'] = $this->sessionSummaryService->getMemories($userId);
		}
		/** @psalm-suppress UndefinedClass */
		$task = new Task(
			\OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID,
			$taskInput,
			Application::APP_ID . ':chatty-llm',
			$userId,
			$customId
		);
		try {
			$this->taskProcessingManager->scheduleTask($task);
		} catch (PreConditionNotMetException $e) {
			throw new BadRequestException('pre_condition_not_met', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\UnauthorizedException $e) {
			throw new BadRequestException('unauthorized', previous: $e);
		} catch (ValidationException $e) {
			throw new BadRequestException('validation_failed', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalException(previous: $e);
		}
		return $task->getId() ?? 0;
	}

	/**
	 * Schedule an audio chat task
	 * @throws BadRequestException
	 * @throws InternalException
	 */
	private function scheduleAudioChatTask(
		?string $userId,
		int $audioFileId,
		string $systemPrompt,
		array $history,
		int $sessionId,
		int $queryMessageId,
	): int {
		$customId = 'chatty-llm:' . $sessionId . ':' . $queryMessageId;
		$this->checkIfSessionIsThinking($userId, $customId);
		$input = [
			'input' => $audioFileId,
			'system_prompt' => $systemPrompt,
			'history' => $history,
		];
		/** @psalm-suppress UndefinedClass */
		if (isset($this->taskProcessingManager->getAvailableTaskTypes()[\OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID]['optionalInputShape']['memories'])) {
			$input['memories'] = $this->sessionSummaryService->getMemories($userId);
		}
		/** @psalm-suppress UndefinedClass */
		$task = new Task(
			\OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID,
			$input,
			Application::APP_ID . ':chatty-llm',
			$userId,
			$customId,
		);
		try {
			$this->taskProcessingManager->scheduleTask($task);
		} catch (PreConditionNotMetException $e) {
			throw new BadRequestException('pre_condition_not_met', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\UnauthorizedException $e) {
			throw new BadRequestException('unauthorized', previous: $e);
		} catch (ValidationException $e) {
			throw new BadRequestException('validation_failed', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalException(previous: $e);
		}
		return $task->getId() ?? 0;
	}

	/**
	 * Schedule an agency audio chat task
	 * @throws BadRequestException
	 * @throws InternalException
	 */
	private function scheduleAgencyAudioTask(
		?string $userId,
		int $audioFileId,
		int $confirmation,
		string $conversationToken,
		int $sessionId,
		int $queryMessageId,
	): int {
		$customId = 'chatty-llm:' . $sessionId . ':' . $queryMessageId;
		$this->checkIfSessionIsThinking($userId, $customId);
		$taskInput = [
			'input' => $audioFileId,
			'confirmation' => $confirmation,
			'conversation_token' => $conversationToken,
		];
		/** @psalm-suppress UndefinedClass */
		if (isset($this->taskProcessingManager->getAvailableTaskTypes()[\OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID]['optionalInputShape']['memories'])) {
			$taskInput['memories'] = $this->sessionSummaryService->getMemories($userId);
		}
		/** @psalm-suppress UndefinedClass */
		$task = new Task(
			\OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID,
			$taskInput,
			Application::APP_ID . ':chatty-llm',
			$userId,
			$customId
		);
		try {
			$this->taskProcessingManager->scheduleTask($task);
		} catch (PreConditionNotMetException $e) {
			throw new BadRequestException('pre_condition_not_met', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\UnauthorizedException $e) {
			throw new BadRequestException('unauthorized', previous: $e);
		} catch (ValidationException $e) {
			throw new BadRequestException('validation_failed', previous: $e);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalException(previous: $e);
		}
		return $task->getId() ?? 0;
	}
}
