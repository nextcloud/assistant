<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\BadRequestException;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\InternalException;
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
use OCP\TaskProcessing\Exception\NotFoundException;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
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
		private ChatService $chatService,
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
			'upload_file' => [
				'title' => $this->l10n->t('Upload file'),
				'icon' => 'Upload',
			],
			'create_folder' => [
				'title' => $this->l10n->t('Create folder'),
				'icon' => 'FolderPlus',
			],
			'move_file' => [
				'title' => $this->l10n->t('Move file'),
				'icon' => 'FileMove',
			],
			'copy_file' => [
				'title' => $this->l10n->t('Copy file'),
				'icon' => 'FileCopy',
			],
			'delete_file' => [
				'title' => $this->l10n->t('Delete file'),
				'icon' => 'Delete',
			],
			'create_bookmark' => [
				'title' => $this->l10n->t('Create bookmark'),
				'icon' => 'BookmarkPlus',
			],
			'update_bookmark' => [
				'title' => $this->l10n->t('Update bookmark'),
				'icon' => 'BookmarkEdit',
			],
			'delete_bookmark' => [
				'title' => $this->l10n->t('Delete bookmark'),
				'icon' => 'BookmarkRemove',
			],
			'create_bookmark_folder' => [
				'title' => $this->l10n->t('Create bookmark folder'),
				'icon' => 'FolderPlus',
			],
			'create_circle' => [
				'title' => $this->l10n->t('Create team'),
				'icon' => 'AccountGroupPlus',
			],
			'add_member_to_circle' => [
				'title' => $this->l10n->t('Add member to team'),
				'icon' => 'AccountPlus',
			],
			'remove_member_from_circle' => [
				'title' => $this->l10n->t('Remove member from team'),
				'icon' => 'AccountRemove',
			],
			'update_circle' => [
				'title' => $this->l10n->t('Update team'),
				'icon' => 'AccountGroupEdit',
			],
			'delete_circle' => [
				'title' => $this->l10n->t('Delete team'),
				'icon' => 'AccountGroupRemove',
			],
			'share_with_circle' => [
				'title' => $this->l10n->t('Share with team'),
				'icon' => 'ShareVariant',
			],
			'create_form' => [
				'title' => $this->l10n->t('Create form'),
				'icon' => 'FormTextbox',
			],
			'add_question_to_form' => [
				'title' => $this->l10n->t('Add question to form'),
				'icon' => 'CommentPlus',
			],
			'delete_form' => [
				'title' => $this->l10n->t('Delete form'),
				'icon' => 'Delete',
			],
			'update_form_settings' => [
				'title' => $this->l10n->t('Update form settings'),
				'icon' => 'Cog',
			],
			'share_with_user' => [
				'title' => $this->l10n->t('Share with user'),
				'icon' => 'AccountShare',
			],
			'share_with_group' => [
				'title' => $this->l10n->t('Share with group'),
				'icon' => 'AccountGroup',
			],
			'update_share_permissions' => [
				'title' => $this->l10n->t('Update share permissions'),
				'icon' => 'LockOpen',
			],
			'delete_share' => [
				'title' => $this->l10n->t('Delete share'),
				'icon' => 'Delete',
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
		try {
			$session = $this->chatService->createChatSession($this->userId, $timestamp, $title);
			return new JSONResponse([
				'session' => $session->jsonSerialize(),
			]);
		} catch (InternalException $e) {
			$this->logger->warning('Failed to create a chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to create a chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('Unauthorized')], Http::STATUS_UNAUTHORIZED);
		}
	}

	/**
	 * Update session title
	 *
	 * Update the title of a chat session
	 *
	 * @param integer $sessionId The chat session ID
	 * @param string $title The new chat session title
	 * @return JSONResponse<Http::STATUS_OK, list{}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: The title has been updated successfully
	 * 404: Session not found
	 * 401: Not logged in
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function updateSessionTitle(int $sessionId, string $title): JSONResponse {
		try {
			$this->chatService->updateSession($this->userId, $sessionId, $title);
			return new JSONResponse();
		} catch (InternalException  $e) {
			$this->logger->warning('Failed to update the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to update the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('Unauthorized')], Http::STATUS_UNAUTHORIZED);
		}
	}

	/**
	 * Update session
	 *
	 * @param integer $sessionId The chat session ID
	 * @param string|null $title The new chat session title
	 * @param bool|null $is_remembered The new is_remembered status: Whether to remember the insights from this chat session across all chat session
	 * @return JSONResponse<Http::STATUS_OK, list{}, array{}>|JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: The title has been updated successfully
	 * 404: The session was not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function updateChatSession(int $sessionId, ?string $title = null, ?bool $is_remembered = null): JSONResponse {
		try {
			$this->chatService->updateSession($this->userId, $sessionId, $title, $is_remembered);
			return new JSONResponse();
		} catch (InternalException $e) {
			$this->logger->warning('Failed to update the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to update the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\NotFoundException|\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('Could not find session')], Http::STATUS_NOT_FOUND);
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
		try {
			// we don't delete the tasks
			$this->chatService->deleteSession($this->userId, $sessionId);
			return new JSONResponse();
		} catch (InternalException $e) {
			$this->logger->warning('Failed to delete the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
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
		try {
			$sessions = $this->chatService->getSessionsForUser($this->userId);
			return new JSONResponse($sessions);
		} catch (InternalException $e) {
			$this->logger->warning('Failed to get chat sessions', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get chat sessions')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
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
		try {
			$message = $this->chatService->createMessage($this->userId, $sessionId, $role, $content, $timestamp, $attachments, $firstHumanMessage);
			return new JSONResponse($message->jsonSerialize());
		} catch (InternalException $e) {
			$this->logger->warning('Failed to add a chat message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to add a chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (BadRequestException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
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
		try {
			$messages = $this->chatService->getSessionMessages($this->userId, $sessionId, $limit, $cursor);
			return new JSONResponse(array_map(static function (Message $message) {
				return $message->jsonSerialize();
			}, $messages));
		} catch (InternalException $e) {
			$this->logger->warning('Failed to get chat messages', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get chat messages')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
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
		try {
			$message = $this->chatService->getSessionMessage($this->userId, $sessionId, $messageId);
			return new JSONResponse($message->jsonSerialize());
		} catch (InternalException $e) {
			$this->logger->warning('Failed to get chat messages', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
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
			$this->chatService->deleteSessionMessage($this->userId, $sessionId, $messageId);
			return new JSONResponse();
		} catch (InternalException $e) {
			$this->logger->warning('Failed to delete a chat message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete a chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
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
		try {
			$taskId = $this->chatService->scheduleMessageGeneration($this->userId, $sessionId, $agencyConfirm);
		} catch (InternalException $e) {
			$this->logger->warning('Failed to schedule message generation', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to schedule message generation')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (BadRequestException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(['taskId' => $taskId]);
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
		try {
			$this->chatService->deleteSessionMessage($this->userId, $sessionId, $messageId);
			$taskId = $this->chatService->scheduleMessageGeneration($this->userId, $sessionId, 0);
			return new JSONResponse(['taskId' => $taskId]);
		} catch (InternalException $e) {
			$this->logger->warning('Failed to delete the last message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the last message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		} catch (BadRequestException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
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
			$task = $this->taskProcessingManager->getUserTask($taskId, $this->userId);
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
			'is_remembered' => $session->getIsRemembered(),
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
	 *
	 * 200: The task has been successfully scheduled
	 * 401: Not logged in
	 * 404: Session was not found
	 * 400: Task was not scheduled
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['chat_api'])]
	public function generateTitle(int $sessionId): JSONResponse {
		try {
			$taskId = $this->chatService->scheduleTitleGeneration($this->userId, $sessionId);
			return new JSONResponse(['taskId' => $taskId]);
		} catch (InternalException|\JsonException $e) {
			$this->logger->warning('Failed to generate a title for the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the last message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCA\Assistant\Service\NotFoundException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\OCA\Assistant\Service\UnauthorizedException $e) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		} catch (BadRequestException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
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
			$task = $this->taskProcessingManager->getUserTask($taskId, $this->userId);
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
					lazy: true,
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
}
