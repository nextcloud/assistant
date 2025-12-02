<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\TaskProcessing\Exception\Exception;
use OCP\TaskProcessing\Exception\NotFoundException;
use OCP\TaskProcessing\Exception\PreConditionNotMetException;
use OCP\TaskProcessing\Exception\UnauthorizedException;
use OCP\TaskProcessing\Exception\ValidationException;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type AssistantChatSession from ResponseDefinitions
 * @psalm-import-type AssistantChatMessage from ResponseDefinitions
 * @psalm-import-type AssistantChatAgencyMessage from ResponseDefinitions
 * @psalm-import-type AssistantChatSessionCheck from ResponseDefinitions
 */
class ChattyLLMController extends OCSController {
	private array $agencyActionData;

	public function __construct(
		string $appName,
		IRequest $request,
		private SessionMapper $sessionMapper,
		private MessageMapper $messageMapper,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private ITaskProcessingManager $taskProcessingManager,
		private IAppConfig $appConfig,
		private IUserManager $userManager,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
		$this->agencyActionData = [
			// talk
			'send_message_to_conversation' => [
				'title' => $this->l10n->t('Send a message to a Talk conversation'),
				'icon' => 'Send',
			],
			'create_public_conversation' => [
				'title' => $this->l10n->t('Create a conversation'),
				'icon' => 'ChatPlus',
			],
			// mail
			'send_email' => [
				'title' => $this->l10n->t('Send an email'),
				'icon' => 'EmailPlus',
			],
			// calendar
			'schedule_event' => [
				'title' => $this->l10n->t('Schedule a calendar event'),
				'icon' => 'CalendarPlus',
			],
			'add_task' => [
				'title' => $this->l10n->t('Add a calendar task'),
				'icon' => 'CalendarCheck',
			],
			// deck
			'add_card' => [
				'title' => $this->l10n->t('Create a Deck card'),
				'icon' => 'CardPlus',
			],
		];
	}

	private function improveAgencyActionNames(array $actions): array {
		return array_map(function ($action) {
			if (isset($action->name, $this->agencyActionData[$action->name])) {
				if (isset($this->agencyActionData[$action->name]['icon'])) {
					$action->icon = $this->agencyActionData[$action->name]['icon'];
				}
				if (isset($this->agencyActionData[$action->name]['title'])) {
					$action->name = $this->agencyActionData[$action->name]['title'];
				}
			}
			return $action;
		}, $actions);
	}

	/**
	 * Create chat session
	 *
	 * Create a new chat session, add a system message with user instructions
	 *
	 * @param int $timestamp The session creation date
	 * @param ?string $title The session title
	 * @return JSONResponse<Http::STATUS_OK, array{session: AssistantChatSession}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 * @throws AppConfigTypeConflictException
	 *
	 * 200: Chat session has been successfully created
	 * 401: User is either not logged in or not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function newSession(int $timestamp, ?string $title = null): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userManager->get($this->userId);
		if ($user === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not found')], Http::STATUS_UNAUTHORIZED);
		}

		$userInstructions = $this->appConfig->getValueString(
			Application::APP_ID,
			'chat_user_instructions',
			Application::CHAT_USER_INSTRUCTIONS,
		) ?: Application::CHAT_USER_INSTRUCTIONS;
		$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);

		try {
			$session = new Session();
			$session->setUserId($this->userId);
			$session->setTitle($title);
			$session->setTimestamp($timestamp);
			$session->setAgencyConversationToken(null);
			$session->setAgencyPendingActions(null);
			$this->sessionMapper->insert($session);

			$systemMsg = new Message();
			$systemMsg->setSessionId($session->getId());
			$systemMsg->setRole('system');
			$systemMsg->setAttachments('[]');
			$systemMsg->setContent($userInstructions);
			$systemMsg->setTimestamp($session->getTimestamp());
			$systemMsg->setSources('[]');
			$this->messageMapper->insert($systemMsg);

			return new JSONResponse([
				'session' => $session->jsonSerialize(),
			]);
		} catch (\OCP\DB\Exception|\RuntimeException $e) {
			$this->logger->warning('Failed to create a chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to create a chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update session title
	 *
	 * Update the title of a chat session
	 *
	 * @param integer $sessionId The chat session ID
	 * @param string $title The new chat session title
	 * @return JSONResponse<Http::STATUS_OK, list{}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: The title has been updated successfully
	 * 401: Not logged in
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function updateSessionTitle(int $sessionId, string $title): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->sessionMapper->updateSessionTitle($this->userId, $sessionId, $title);
			return new JSONResponse();
		} catch (\OCP\DB\Exception|\RuntimeException  $e) {
			$this->logger->warning('Failed to update the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to update the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a chat session
	 *
	 * Delete a chat session by ID
	 *
	 * @param integer $sessionId The session ID
	 * @return JSONResponse<Http::STATUS_OK, list{}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: The session has been deleted successfully
	 * 401: Not logged in
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function deleteSession(int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->deleteSessionTasks($this->userId, $sessionId);
			$this->sessionMapper->deleteSession($this->userId, $sessionId);
			$this->messageMapper->deleteMessagesBySession($sessionId);
			return new JSONResponse();
		} catch (\OCP\DB\Exception|\RuntimeException  $e) {
			$this->logger->warning('Failed to delete the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function deleteSessionTasks(string $userId, int $sessionId): void {
		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return;
		}
		$messages = $this->messageMapper->getMessages($sessionId, 0, 0);
		foreach ($messages as $message) {
			$ocpTaskId = $message->getOcpTaskId();
			if ($ocpTaskId !== 0) {
				try {
					$task = $this->taskProcessingManager->getTask($ocpTaskId);
					$this->taskProcessingManager->deleteTask($task);
				} catch (\OCP\TaskProcessing\Exception\Exception) {
					// silent failure here because:
					// if the task is not found: all good nothing to delete
					// if the task couldn't be deleted, it will be deleted by the task processing cleanup job later anyway
				}
			}
		}
	}

	/**
	 * Get chat sessions
	 *
	 * Get all chat sessions for the current user
	 *
	 * @return JSONResponse<Http::STATUS_OK, list<AssistantChatSession>, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: The session list has been obtained successfully
	 * 401: Not logged in
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function getSessions(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessions = $this->sessionMapper->getUserSessions($this->userId);
			return new JSONResponse($sessions);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to get chat sessions', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get chat sessions')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Add a message
	 *
	 * Add a new chat message to the session
	 *
	 * @param int $sessionId The chat session ID
	 * @param string $role Role of the message (human, assistant etc...)
	 * @param string $content Content of the message
	 * @param int $timestamp Date of the message
	 * @param ?list<array{type: string, file_id: int}> $attachments List of attachment objects
	 * @param bool $firstHumanMessage Is it the first human message of the session?
	 * @return JSONResponse<Http::STATUS_OK, AssistantChatMessage, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: The session list has been obtained successfully
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Message is malformed
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function newMessage(
		int $sessionId, string $role, string $content, int $timestamp, ?array $attachments = null, bool $firstHumanMessage = false,
	): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}
		if (strlen($content) > Application::MAX_TEXT_INPUT_LENGTH) {
			return new JSONResponse(['error' => $this->l10n->t('The new message is too long')], Http::STATUS_BAD_REQUEST);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			// refuse empty text content if context agent is not available (we do classic chat) AND there is no attachment
			// in other words: accept empty content if we are using agency OR there are attachments
			$content = trim($content);
			if (empty($content)
				&& (!class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentInteraction')
					|| !isset($this->taskProcessingManager->getAvailableTaskTypes()[\OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID]))
				&& $attachments === null
			) {
				return new JSONResponse(['error' => $this->l10n->t('Message content is empty')], Http::STATUS_BAD_REQUEST);
			}

			$message = new Message();
			$message->setSessionId($sessionId);
			$message->setRole($role);
			$message->setContent($content);
			$message->setTimestamp($timestamp);
			$message->setSources('[]');
			$message->setAttachments('[]');
			if ($attachments !== null) {
				$encodedAttachments = json_encode($attachments);
				if ($encodedAttachments !== false) {
					$message->setAttachments($encodedAttachments);
				}
			}
			$this->messageMapper->insert($message);

			if ($firstHumanMessage) {
				// set the title of the session based on first human message
				$this->sessionMapper->updateSessionTitle(
					$this->userId,
					$sessionId,
					strlen($content) > 140 ? mb_substr($content, 0, 140) . '...' : $content,
				);
			}

			return new JSONResponse($message->jsonSerialize());
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to add a chat message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to add a chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get session messages
	 *
	 * Get chat messages for the session without the system message
	 *
	 * @param int $sessionId The session ID
	 * @param int $limit The max number of messages to return
	 * @param int $cursor The index of the first result to return
	 * @return JSONResponse<Http::STATUS_OK, list<AssistantChatMessage>, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: The message list has been successfully obtained
	 * 401: Not logged in
	 * 404: The session was not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function getMessages(int $sessionId, int $limit = 20, int $cursor = 0): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			/** @var list<Message> $messages */
			$messages = $this->messageMapper->getMessages($sessionId, $cursor, $limit);
			if ($messages[0]->getRole() === 'system') {
				array_shift($messages);
			}

			return new JSONResponse(array_map(static function (Message $message) { return $message->jsonSerialize(); }, $messages));
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to get chat messages', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get chat messages')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a message
	 *
	 * Get a chat message in a session
	 *
	 * @param int $sessionId The session ID
	 * @param int $messageId The message ID
	 * @return JSONResponse<Http::STATUS_OK, AssistantChatMessage, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: The message has been successfully obtained
	 * 401: Not logged in
	 * 404: The session or the message was not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function getMessage(int $sessionId, int $messageId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			$message = $this->messageMapper->getMessageById($sessionId, $messageId);

			return new JSONResponse($message->jsonSerialize());
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to get chat messages', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a message
	 *
	 * Delete a chat message by ID
	 *
	 * @param integer $messageId The message ID
	 * @param integer $sessionId The session ID
	 * @return JSONResponse<Http::STATUS_OK, list{}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: The message has been successfully deleted
	 * 401: Not logged in
	 * 404: The session was not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function deleteMessage(int $messageId, int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}
			$message = $this->messageMapper->getMessageById($sessionId, $messageId);
			$ocpTaskId = $message->getOcpTaskId();

			$this->messageMapper->deleteMessageById($sessionId, $messageId);

			// delete the related task
			if ($ocpTaskId !== 0) {
				try {
					$task = $this->taskProcessingManager->getTask($ocpTaskId);
					$this->taskProcessingManager->deleteTask($task);
				} catch (\OCP\TaskProcessing\Exception\Exception) {
				}
			}
			return new JSONResponse();
		} catch (\OCP\DB\Exception|\RuntimeException $e) {
			$this->logger->warning('Failed to delete a chat message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete a chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Generate a new assistant message
	 *
	 * Schedule a task to generate a new message for a session
	 *
	 * @param integer $sessionId The session ID
	 * @param int $agencyConfirm Potential agency sensitive actions confirmation (1: accept, 0: reject)
	 * @return JSONResponse<Http::STATUS_OK, array{taskId: int}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws AppConfigTypeConflictException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 *
	 * 200: The task has been successfully scheduled
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Task was not scheduled
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function generateForSession(int $sessionId, int $agencyConfirm = 0): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		if (class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentInteraction')
			&& isset($this->taskProcessingManager->getAvailableTaskTypes()[\OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID])
		) {
			$lastUserMessage = $this->messageMapper->getLastHumanMessage($sessionId);
			$session = $this->sessionMapper->getUserSession($this->userId, $sessionId);
			$lastConversationToken = $session->getAgencyConversationToken() ?? '{}';

			$lastAttachments = $lastUserMessage->jsonSerialize()['attachments'];
			$audioAttachment = $lastAttachments[0] ?? null;
			// see https://github.com/vimeo/psalm/issues/7980
			$isContextAgentAudioAvailable = false;
			if (class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentAudioInteraction')) {
				$isContextAgentAudioAvailable = isset($this->taskProcessingManager->getAvailableTaskTypes()[\OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID]);
			}
			if ($audioAttachment !== null
				&& isset($audioAttachment['type'])
				&& $audioAttachment['type'] === 'Audio'
				&& $isContextAgentAudioAvailable
			) {
				// audio agency
				$fileId = $audioAttachment['file_id'];
				try {
					$taskId = $this->scheduleAgencyAudioTask($fileId, $agencyConfirm, $lastConversationToken, $sessionId, $lastUserMessage->getId());
				} catch (\Exception $e) {
					return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}
			} else {
				// classic agency
				$prompt = $lastUserMessage->getContent();
				try {
					$taskId = $this->scheduleAgencyTask($prompt, $agencyConfirm, $lastConversationToken, $sessionId);
				} catch (\Exception $e) {
					return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}
			}
		} else {
			// classic chat
			$systemPrompt = '';
			$firstMessage = $this->messageMapper->getFirstNMessages($sessionId, 1);
			if ($firstMessage->getRole() === 'system') {
				$systemPrompt = $firstMessage->getContent();
			}
			$history = $this->getRawLastMessages($sessionId);
			do {
				$lastUserMessage = array_pop($history);
			} while ($lastUserMessage->getRole() !== 'human');

			$lastAttachments = $lastUserMessage->jsonSerialize()['attachments'];
			$audioAttachment = $lastAttachments[0] ?? null;
			$isAudioToAudioAvailable = false;
			if (class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat')) {
				$isAudioToAudioAvailable = isset($this->taskProcessingManager->getAvailableTaskTypes()[\OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID]);
			}
			if ($audioAttachment !== null
				&& isset($audioAttachment['type'])
				&& $audioAttachment['type'] === 'Audio'
				&& $isAudioToAudioAvailable
			) {
				// for an audio chat task, let's try to get the remote audio IDs for all the previous audio messages
				$history = $this->getAudioHistory($history);
				$fileId = $audioAttachment['file_id'];
				try {
					$taskId = $this->scheduleAudioChatTask($fileId, $systemPrompt, $history, $sessionId, $lastUserMessage->getId());
				} catch (\Exception $e) {
					return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}
			} else {
				// for a text chat task, let's only use text in the history
				$history = array_map(static function (Message $message) {
					return json_encode([
						'role' => $message->getRole(),
						'content' => $message->getContent(),
					]);
				}, $history);
				try {
					$taskId = $this->scheduleLLMChatTask($lastUserMessage->getContent(), $systemPrompt, $history, $sessionId);
				} catch (\Exception $e) {
					return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}
			}
		}

		return new JSONResponse(['taskId' => $taskId]);
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
			if ($message->getRole() === 'assistant'
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
	 * Regenerate response for a message
	 *
	 * Delete the message with the given message ID and all following ones,
	 * then schedule a task to generate a new message for the session
	 *
	 * @param int $sessionId The chat session ID
	 * @param int $messageId The chat message ID
	 * @return JSONResponse<Http::STATUS_OK, array{taskId: int}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws AppConfigTypeConflictException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 *
	 * 200: The task has been successfully scheduled
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Task was not scheduled
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function regenerateForSession(int $sessionId, int $messageId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		$message = $this->messageMapper->getMessageById($sessionId, $messageId);
		$ocpTaskId = $message->getOcpTaskId();

		try {
			$this->messageMapper->deleteMessageById($sessionId, $messageId);
		} catch (\OCP\DB\Exception|\RuntimeException $e) {
			$this->logger->warning('Failed to delete the last message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the last message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// delete the related task
		if ($ocpTaskId !== 0) {
			try {
				$task = $this->taskProcessingManager->getTask($ocpTaskId);
				$this->taskProcessingManager->deleteTask($task);
			} catch (\OCP\TaskProcessing\Exception\Exception) {
			}
		}

		return $this->generateForSession($sessionId);
	}

	/**
	 * Check the status of a generation task. The value of slow_pickup will be set to true if the task is not being picked up.
	 *
	 * Used by the frontend to poll a generation task status. If the task succeeds, a new message is stored and returned.
	 *
	 * @param int $taskId The message generation task ID
	 * @param int $sessionId The chat session ID
	 * @return JSONResponse<Http::STATUS_OK, AssistantChatAgencyMessage, array{}>|JSONResponse<Http::STATUS_EXPECTATION_FAILED, array{task_status: int, slow_pickup: bool}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 *
	 * 200: The task was successful, a message has been generated
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Task processing failed
	 * 417: The task is still running or has not been picked up yet
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function checkMessageGenerationTask(int $taskId, int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		try {
			$task = $this->taskProcessingManager->getTask($taskId);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'task_not_found'], Http::STATUS_NOT_FOUND);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			return new JSONResponse(['error' => 'task_query_failed'], Http::STATUS_BAD_REQUEST);
		}
		if ($task->getStatus() === Task::STATUS_SUCCESSFUL) {
			try {
				$message = $this->messageMapper->getMessageByTaskId($sessionId, $taskId);
				$jsonMessage = $message->jsonSerialize();
				$session = $this->sessionMapper->getUserSession($this->userId, $sessionId);
				$jsonMessage['sessionAgencyPendingActions'] = $session->getAgencyPendingActions();
				if ($jsonMessage['sessionAgencyPendingActions'] !== null) {
					$jsonMessage['sessionAgencyPendingActions'] = json_decode($jsonMessage['sessionAgencyPendingActions']);
					$jsonMessage['sessionAgencyPendingActions'] = $this->improveAgencyActionNames($jsonMessage['sessionAgencyPendingActions']);
				}
				// do not insert here, it is done by the listener
				return new JSONResponse($jsonMessage);
			} catch (\OCP\DB\Exception $e) {
				$this->logger->warning('Failed to add a chat message into the DB', ['exception' => $e]);
				return new JSONResponse(['error' => $this->l10n->t('Failed to add a chat message into DB')], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException $e) {
				$this->logger->debug('Task finished successfully but failed to find the chat message in the DB. It should be created soon.', ['exception' => $e]);
				return new JSONResponse(['task_status' => $task->getstatus()], Http::STATUS_EXPECTATION_FAILED);
			}
		} elseif ($task->getstatus() === Task::STATUS_RUNNING || $task->getstatus() === Task::STATUS_SCHEDULED) {
			$startTime = $task->getStartedAt() ?? time();
			$slowPickup = ($task->getScheduledAt() + (60 * 5)) < $startTime;
			return new JSONResponse(['task_status' => $task->getstatus(), 'slow_pickup' => $slowPickup], Http::STATUS_EXPECTATION_FAILED);
		} elseif ($task->getstatus() === Task::STATUS_FAILED || $task->getstatus() === Task::STATUS_CANCELLED) {
			return new JSONResponse(['error' => 'task_failed_or_canceled', 'task_status' => $task->getstatus()], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['error' => 'unknown_error', 'task_status' => $task->getstatus()], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Check the status of a session
	 *
	 * Used by the frontend to determine if it should poll a generation task status.
	 *
	 * @param int $sessionId The chat session ID
	 * @return JSONResponse<Http::STATUS_OK, AssistantChatSessionCheck, array{}>|JSONResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \JsonException
	 * @throws \OCP\DB\Exception
	 *
	 * 200: The session status has been successfully obtained
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Task processing failed, impossible to check the related tasks
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function checkSession(int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		try {
			$messageTasks = $this->taskProcessingManager->getUserTasksByApp($this->userId, Application::APP_ID . ':chatty-llm', 'chatty-llm:' . $sessionId);
			$titleTasks = $this->taskProcessingManager->getUserTasksByApp($this->userId, Application::APP_ID . ':chatty-llm', 'chatty-title:' . $sessionId);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			return new JSONResponse(['error' => 'task_query_failed'], Http::STATUS_BAD_REQUEST);
		}
		$messageTasks = array_filter($messageTasks, static function (Task $task) {
			return $task->getStatus() === Task::STATUS_RUNNING || $task->getStatus() === Task::STATUS_SCHEDULED;
		});
		$titleTasks = array_filter($titleTasks, static function (Task $task) {
			return $task->getStatus() === Task::STATUS_RUNNING || $task->getStatus() === Task::STATUS_SCHEDULED;
		});
		$session = $this->sessionMapper->getUserSession($this->userId, $sessionId);
		$pendingActions = $session->getAgencyPendingActions();
		if ($pendingActions !== null) {
			$pendingActions = json_decode($pendingActions);
			$pendingActions = $this->improveAgencyActionNames($pendingActions);
		}
		/** @var ?array<string, mixed> $p */
		$p = $pendingActions;
		$responseData = [
			'messageTaskId' => null,
			'titleTaskId' => null,
			'sessionTitle' => $session->getTitle(),
			'sessionAgencyPendingActions' => $p,
		];
		if (!empty($messageTasks)) {
			$task = array_pop($messageTasks);
			$responseData['messageTaskId'] = $task->getId();
		}
		if (!empty($titleTasks)) {
			$task = array_pop($titleTasks);
			$responseData['titleTaskId'] = $task->getId();
		}
		return new JSONResponse($responseData);
	}

	/**
	 * Generate a session title
	 *
	 * Schedule a task to generate a title for a chat session
	 *
	 * @param integer $sessionId The chat session ID
	 * @return JSONResponse<Http::STATUS_OK, array{taskId: int}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws AppConfigTypeConflictException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 *
	 * 200: The task has been successfully scheduled
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Task was not scheduled
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function generateTitle(int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userManager->get($this->userId);
		if ($user === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not found')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		try {
			$userInstructions = $this->appConfig->getValueString(
				Application::APP_ID,
				'chat_user_instructions_title',
				Application::CHAT_USER_INSTRUCTIONS_TITLE,
			) ?: Application::CHAT_USER_INSTRUCTIONS_TITLE;
			$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);

			$systemPrompt = '';
			$firstMessage = $this->messageMapper->getFirstNMessages($sessionId, 1);
			if ($firstMessage->getRole() === 'system') {
				$systemPrompt = $firstMessage->getContent();
			}

			$history = $this->getRawLastMessages($sessionId);
			// history is a list of JSON strings
			$history = array_map(static function (Message $message) {
				return json_encode([
					'role' => $message->getRole(),
					'content' => $message->getContent(),
				]);
			}, $history);

			try {
				$taskId = $this->scheduleLLMChatTask($userInstructions, $systemPrompt, $history, $sessionId, false);
			} catch (\Exception $e) {
				return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
			}
			return new JSONResponse(['taskId' => $taskId]);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to generate a title for the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to generate a title for the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Check the status of a title generation task
	 *
	 * Used by the frontend to poll a title generation task status. If the task succeeds, the new title is set and returned.
	 *
	 * @param int $taskId The title generation task ID
	 * @param int $sessionId The chat session ID
	 * @return JSONResponse<Http::STATUS_OK, array{result: string}, array{}>|JSONResponse<Http::STATUS_EXPECTATION_FAILED, array{task_status: int}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws AppConfigTypeConflictException
	 * @throws \OCP\DB\Exception 200: The task was successful, a message has been generated
	 *
	 * 200: Title has been successfully generated
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Task processing failed
	 * 417: The task is still running or has not been picked up yet
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function checkTitleGenerationTask(int $taskId, int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$user = $this->userManager->get($this->userId);
		if ($user === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not found')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		try {
			$task = $this->taskProcessingManager->getTask($taskId);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'task_not_found'], Http::STATUS_NOT_FOUND);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			return new JSONResponse(['error' => 'task_query_failed'], Http::STATUS_BAD_REQUEST);
		}

		if ($task->getStatus() === Task::STATUS_SUCCESSFUL) {
			try {
				$taskOutput = trim($task->getOutput()['output'] ?? '');
				$userInstructions = $this->appConfig->getValueString(
					Application::APP_ID,
					'chat_user_instructions_title',
					Application::CHAT_USER_INSTRUCTIONS_TITLE,
				) ?: Application::CHAT_USER_INSTRUCTIONS_TITLE;
				$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);
				$title = str_replace($userInstructions, '', $taskOutput);
				$title = str_replace('"', '', $title);
				$title = explode(PHP_EOL, $title)[0];
				$title = trim($title);
				// do not write the title here since it's done in the listener

				return new JSONResponse(['result' => $title]);
			} catch (\OCP\DB\Exception $e) {
				$this->logger->warning('Failed to generate a title for the chat session', ['exception' => $e]);
				return new JSONResponse(['error' => $this->l10n->t('Failed to generate a title for the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		} elseif ($task->getstatus() === Task::STATUS_RUNNING || $task->getstatus() === Task::STATUS_SCHEDULED) {
			return new JSONResponse(['task_status' => $task->getstatus()], Http::STATUS_EXPECTATION_FAILED);
		} elseif ($task->getstatus() === Task::STATUS_FAILED || $task->getstatus() === Task::STATUS_CANCELLED) {
			return new JSONResponse(['error' => 'task_failed_or_canceled', 'task_status' => $task->getstatus()], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['error' => 'unknown_error', 'task_status' => $task->getstatus()], Http::STATUS_BAD_REQUEST);
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
		$lastNMessages = intval($this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10'));
		$messages = $this->messageMapper->getMessages($sessionId, 0, $lastNMessages);

		if ($messages[0]->getRole() === 'system') {
			array_shift($messages);
		}
		return $messages;
	}

	private function checkIfSessionIsThinking(string $customId): void {
		try {
			$tasks = $this->taskProcessingManager->getUserTasksByApp($this->userId, Application::APP_ID . ':chatty-llm', $customId);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			throw new \Exception('task_query_failed');
		}
		$tasks = array_filter($tasks, static function (Task $task) {
			return $task->getStatus() === Task::STATUS_RUNNING || $task->getStatus() === Task::STATUS_SCHEDULED;
		});
		// prevent scheduling multiple llm tasks simultaneously for one session
		if (!empty($tasks)) {
			throw new \Exception('session_already_thinking');
		}
	}

	/**
	 * Schedule the LLM task
	 *
	 * @param string $newPrompt
	 * @param string $systemPrompt
	 * @param array $history
	 * @param int $sessionId
	 * @param bool $isMessage whether we want to generate a message or a session title
	 * @return int
	 * @throws Exception
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 */
	private function scheduleLLMChatTask(
		string $newPrompt, string $systemPrompt, array $history, int $sessionId, bool $isMessage = true,
	): int {
		$customId = ($isMessage
			? 'chatty-llm:'
			: 'chatty-title:') . $sessionId;
		$this->checkIfSessionIsThinking($customId);
		$input = [
			'input' => $newPrompt,
			'system_prompt' => $systemPrompt,
			'history' => $history,
		];
		$task = new Task(TextToTextChat::ID, $input, Application::APP_ID . ':chatty-llm', $this->userId, $customId);
		$this->taskProcessingManager->scheduleTask($task);
		return $task->getId() ?? 0;
	}

	/**
	 * Schedule an agency task
	 *
	 * @param string $content
	 * @param int $confirmation
	 * @param string $conversationToken
	 * @param int $sessionId
	 * @return int
	 * @throws Exception
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 */
	private function scheduleAgencyTask(string $content, int $confirmation, string $conversationToken, int $sessionId): int {
		$customId = 'chatty-llm:' . $sessionId;
		$this->checkIfSessionIsThinking($customId);
		$taskInput = [
			'input' => $content,
			'confirmation' => $confirmation,
			'conversation_token' => $conversationToken,
		];
		/** @psalm-suppress UndefinedClass */
		$task = new Task(
			\OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID,
			$taskInput,
			Application::APP_ID . ':chatty-llm',
			$this->userId,
			$customId
		);
		$this->taskProcessingManager->scheduleTask($task);
		return $task->getId() ?? 0;
	}

	private function scheduleAudioChatTask(
		int $audioFileId, string $systemPrompt, array $history, int $sessionId, int $queryMessageId,
	): int {
		$customId = 'chatty-llm:' . $sessionId . ':' . $queryMessageId;
		$this->checkIfSessionIsThinking($customId);
		$input = [
			'input' => $audioFileId,
			'system_prompt' => $systemPrompt,
			'history' => $history,
		];
		/** @psalm-suppress UndefinedClass */
		$task = new Task(
			\OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID,
			$input,
			Application::APP_ID . ':chatty-llm',
			$this->userId,
			$customId,
		);
		$this->taskProcessingManager->scheduleTask($task);
		return $task->getId() ?? 0;
	}

	private function scheduleAgencyAudioTask(
		int $audioFileId, int $confirmation, string $conversationToken, int $sessionId, int $queryMessageId,
	): int {
		$customId = 'chatty-llm:' . $sessionId . ':' . $queryMessageId;
		$this->checkIfSessionIsThinking($customId);
		$taskInput = [
			'input' => $audioFileId,
			'confirmation' => $confirmation,
			'conversation_token' => $conversationToken,
		];
		/** @psalm-suppress UndefinedClass */
		$task = new Task(
			\OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID,
			$taskInput,
			Application::APP_ID . ':chatty-llm',
			$this->userId,
			$customId
		);
		$this->taskProcessingManager->scheduleTask($task);
		return $task->getId() ?? 0;
	}
}
