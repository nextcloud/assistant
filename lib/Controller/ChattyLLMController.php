<?php

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
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
use OCP\TaskProcessing\TaskTypes\TextToText;
use Psr\Log\LoggerInterface;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class ChattyLLMController extends Controller {

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
	}

	/**
	 * Create a new chat session, add a system message with user instructions
	 *
	 * @param int $timestamp
	 * @param ?string $title
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
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
		);
		$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);

		try {
			$session = new Session();
			$session->setUserId($this->userId);
			$session->setTitle($title);
			$session->setTimestamp($timestamp);
			$this->sessionMapper->insert($session);

			$systemMsg = new Message();
			$systemMsg->setSessionId($session->getId());
			$systemMsg->setRole('system');
			$systemMsg->setContent($userInstructions);
			$systemMsg->setTimestamp($session->getTimestamp());
			$this->messageMapper->insert($systemMsg);

			return new JSONResponse([
				'session' => $session,
			]);
		} catch (\OCP\DB\Exception | \RuntimeException $e) {
			$this->logger->warning('Failed to create a chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to create a chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update the title of the chat session
	 *
	 * @param integer $sessionId
	 * @param string $title
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function updateSessionTitle(int $sessionId, string $title): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->sessionMapper->updateSessionTitle($this->userId, $sessionId, $title);
			return new JSONResponse();
		} catch (\OCP\DB\Exception | \RuntimeException  $e) {
			$this->logger->warning('Failed to update the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to update the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a chat session by ID
	 *
	 * @param integer $sessionId
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function deleteSession(int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->sessionMapper->deleteSession($this->userId, $sessionId);
			$this->messageMapper->deleteMessagesBySession($sessionId);
			return new JSONResponse();
		} catch (\OCP\DB\Exception | \RuntimeException  $e) {
			$this->logger->warning('Failed to delete the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all chat sessions for the user
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
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
	 * Add a new chat message to the session
	 *
	 * @param int $sessionId
	 * @param string $role
	 * @param string $content
	 * @param int $timestamp
	 * @param bool $firstHumanMessage
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function newMessage(int $sessionId, string $role, string $content, int $timestamp, bool $firstHumanMessage = false): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			$content = trim($content);
			if (empty($content)) {
				return new JSONResponse(['error' => $this->l10n->t('Message content is empty')], Http::STATUS_BAD_REQUEST);
			}

			$message = new Message();
			$message->setSessionId($sessionId);
			$message->setRole($role);
			$message->setContent($content);
			$message->setTimestamp($timestamp);
			$this->messageMapper->insert($message);

			if ($firstHumanMessage) {
				// set the title of the session based on first human message
				$this->sessionMapper->updateSessionTitle(
					$this->userId,
					$sessionId,
					strlen($content) > 140 ? mb_substr($content, 0, 140) . '...' : $content,
				);
			}

			return new JSONResponse($message);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to add a chat message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to add a chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get chat messages for the session without the system message
	 *
	 * @param int $sessionId
	 * @param int $limit
	 * @param int $cursor
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function getMessages(int $sessionId, int $limit = 20, int $cursor = 0): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			$messages = $this->messageMapper->getMessages($sessionId, $cursor, $limit);
			if ($messages[0]->getRole() === 'system') {
				array_shift($messages);
			}

			return new JSONResponse($messages);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to get chat messages', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get chat messages')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a chat message by ID
	 *
	 * @param integer $messageId
	 * @param integer $sessionId
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function deleteMessage(int $messageId, int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			$this->messageMapper->deleteMessageById($messageId);
			return new JSONResponse();
		} catch (\OCP\DB\Exception | \RuntimeException $e) {
			$this->logger->warning('Failed to delete a chat message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete a chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Schedule a task to generate a new message for the session
	 *
	 * @param integer $sessionId
	 * @return JSONResponse
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundException
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\TaskProcessing\Exception\Exception
	 */
	#[NoAdminRequired]
	public function generateForSession(int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		$stichedPrompt =
			$this->getStichedMessages($sessionId)
			. PHP_EOL
			. 'assistant: ';

		$taskId = $this->scheduleLLMTask($stichedPrompt);

		return new JSONResponse(['taskId' => $taskId]);
	}

	/**
	 * Delete all messages since the given message ID and then
	 * schedule a task to generate a new message for the session
	 *
	 * @param int $sessionId
	 * @param int $messageId
	 * @return JSONResponse
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundException
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\TaskProcessing\Exception\Exception
	 */
	#[NoAdminRequired]
	public function regenerateForSession(int $sessionId, int $messageId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
		if (!$sessionExists) {
			return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->messageMapper->deleteMessageById($messageId);
		} catch (\OCP\DB\Exception|\RuntimeException $e) {
			$this->logger->warning('Failed to delete the last message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the last message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return $this->generateForSession($sessionId);
	}

	/**
	 * Check the status of a generation task
	 *
	 * Used by the frontend to poll a generation task status. If the task succeeds, a new message is stored and returned.
	 *
	 * @param int $taskId
	 * @param int $sessionId
	 * @return JSONResponse
	 * @throws \OCP\DB\Exception
	 */
	#[NoAdminRequired]
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
				$message = new Message();
				$message->setSessionId($sessionId);
				$message->setRole('assistant');
				$message->setContent(trim($task->getOutput()['output'] ?? ''));
				$message->setTimestamp(time());
				$this->messageMapper->insert($message);
				return new JSONResponse($message);
			} catch (\OCP\DB\Exception $e) {
				$this->logger->warning('Failed to add a chat message into DB', ['exception' => $e]);
				return new JSONResponse(['error' => $this->l10n->t('Failed to add a chat message into DB')], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		} elseif ($task->getstatus() === Task::STATUS_RUNNING || $task->getstatus() === Task::STATUS_SCHEDULED) {
			return new JSONResponse(['task_status' => $task->getstatus()], Http::STATUS_EXPECTATION_FAILED);
		} elseif ($task->getstatus() === Task::STATUS_FAILED || $task->getstatus() === Task::STATUS_CANCELLED) {
			return new JSONResponse(['error' => 'task_failed_or_canceled', 'task_status' => $task->getstatus()], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['error' => 'unknown_error', 'task_status' => $task->getstatus()], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Schedule a task to generate a title for the chat session
	 *
	 * @param integer $sessionId
	 * @return JSONResponse
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundException
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\TaskProcessing\Exception\Exception
	 */
	#[NoAdminRequired]
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
			);
			$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);

			$stichedPrompt = $this->getStichedMessages($sessionId)
				. PHP_EOL . PHP_EOL
				. $userInstructions;

			$taskId = $this->scheduleLLMTask($stichedPrompt);
			return new JSONResponse(['taskId' => $taskId]);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to generate a title for the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to generate a title for the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
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
				);
				$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);
				$title = str_replace($userInstructions, '', $taskOutput);
				$title = str_replace('"', '', $title);
				$title = explode(PHP_EOL, $title)[0];
				$title = trim($title);

				$this->sessionMapper->updateSessionTitle($this->userId, $sessionId, $title);

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
	 * Get the first message (user instructions) and the last N messages (assistant and user messages)
	 * and stich them together
	 *
	 * @param integer $sessionId
	 * @return string
	 * @throws \OCP\DB\Exception
	 * @throws \RuntimeException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	private function getStichedMessages(int $sessionId): string {
		$stichedPrompt = '';

		$firstMessage = $this->messageMapper->getFirstNMessages($sessionId, 1);
		if ($firstMessage->getRole() === 'system') {
			$stichedPrompt = $firstMessage->getContent() . PHP_EOL;
		}

		$lastNMessages = intval($this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10'));
		$messages = $this->messageMapper->getMessages($sessionId, 0, $lastNMessages);

		if ($messages[0]->getRole() === 'system') {
			array_shift($messages);
		}
		$stichedPrompt .= implode(PHP_EOL, array_map(fn ($msg) => $msg->getContent(), $messages));

		return $stichedPrompt;
	}

	/**
	 * Schedule the LLM task
	 *
	 * @param string $content
	 * @return int|null
	 * @throws Exception
	 * @throws PreConditionNotMetException
	 * @throws UnauthorizedException
	 * @throws ValidationException
	 */
	private function scheduleLLMTask(string $content): ?int {
		$task = new Task(TextToText::ID, ['input' => $content], Application::APP_ID . ':chatty-llm', $this->userId);
		$this->taskProcessingManager->scheduleTask($task);
		return $task->getId();
	}
}
