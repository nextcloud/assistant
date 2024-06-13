<?php

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AssistantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\TextProcessing\FreePromptTaskType;
use OCP\TextProcessing\IManager as ITextProcessingManager;
use OCP\TextProcessing\Task as TextProcessingTask;
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
		private AssistantService $assistantService,
		private ITextProcessingManager $textProcessingManager,
		private IConfig $config,
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

		$userInstructions = $this->config->getAppValue(
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
			$this->sessionMapper->updateSessionTitle($sessionId, $title);
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
			$this->sessionMapper->deleteSession($sessionId);
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
	 * @param bool $firstMessage
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function newMessage(int $sessionId, string $role, string $content, int $timestamp, bool $firstHumanMessage = false): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($sessionId);
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
				$this->sessionMapper->updateSessionTitle($sessionId, strlen($content) > 140 ? mb_substr($content, 0, 140) . '...' : $content);
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
			$sessionExists = $this->sessionMapper->exists($sessionId);
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
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function deleteMessage(int $messageId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->messageMapper->deleteMessageById($messageId);
			return new JSONResponse();
		} catch (\OCP\DB\Exception | \RuntimeException $e) {
			$this->logger->warning('Failed to delete a chat message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete a chat message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Generate a new message for the session
	 *
	 * @param integer $sessionId
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function generateForSession(int $sessionId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			$stichedPrompt =
				$this->getStichedMessages($sessionId)
				. PHP_EOL
				. 'assistant: ';

			$result = $this->queryLLM($stichedPrompt);

			$message = new Message();
			$message->setSessionId($sessionId);
			$message->setRole('assistant');
			$message->setContent($result);
			$message->setTimestamp(time());
			$this->messageMapper->insert($message);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to add a chat message into DB', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to add a chat message into DB')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse($message);
	}

	/**
	 * Delete all messages since the given message ID and then
	 * generate a new message for the session
	 *
	 * @param integer $sessionId
	 * @param integer $messageId
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function regenerateForSession(int $sessionId, int $messageId): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l10n->t('User not logged in')], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$sessionExists = $this->sessionMapper->exists($sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			$lastMessageBackup = $this->messageMapper->getMessageById($messageId);
		} catch (\OCP\DB\Exception | \RuntimeException $e) {
			$this->logger->warning('Failed to get the last message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get the last message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\OCP\AppFramework\Db\DoesNotExistException | \OCP\AppFramework\Db\MultipleObjectsReturnedException $e) {
			$this->logger->warning('Failed to get the last message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to get the last message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$this->messageMapper->deleteMessageByIdAndSessionId($messageId, $sessionId);
		} catch (\OCP\DB\Exception | \RuntimeException $e) {
			$this->logger->warning('Failed to delete the last message', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to delete the last message')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$newMessage = $this->generateForSession($sessionId);

			return $newMessage;
		} catch (\OCP\DB\Exception | \OCP\TextProcessing\Exception\TaskFailureException $e) {
			try {
				$lastMessageBackupCopy = new Message();
				$lastMessageBackupCopy->setSessionId($lastMessageBackup->getSessionId());
				$lastMessageBackupCopy->setRole($lastMessageBackup->getRole());
				$lastMessageBackupCopy->setContent($lastMessageBackup->getContent());
				$lastMessageBackupCopy->setTimestamp($lastMessageBackup->getTimestamp());

				// add the last message back
				$this->messageMapper->insert($lastMessageBackupCopy);

				if ($e instanceof \OCP\TextProcessing\Exception\TaskFailureException) {
					throw $e;
				}
			} catch (\OCP\DB\Exception $dbException) {
				$this->logger->warning('Failed to add the last message back', ['exception' => $dbException]);
			}

			$this->logger->warning('Failed to add a chat message into DB', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to add a chat message into DB')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Generate a title for the chat session
	 *
	 * @param integer $sessionId
	 * @return JSONResponse
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

		try {
			$sessionExists = $this->sessionMapper->exists($sessionId);
			if (!$sessionExists) {
				return new JSONResponse(['error' => $this->l10n->t('Session not found')], Http::STATUS_NOT_FOUND);
			}

			$userInstructions = $this->config->getAppValue(
				Application::APP_ID,
				'chat_user_instructions_title',
				Application::CHAT_USER_INSTRUCTIONS_TITLE,
			);
			$userInstructions = str_replace('{user}', $user->getDisplayName(), $userInstructions);

			$stichedPrompt = $this->getStichedMessages($sessionId)
				. PHP_EOL . PHP_EOL
				. $userInstructions;

			$result = $this->queryLLM($stichedPrompt);
			$title = str_replace($userInstructions, '', $result);
			$title = str_replace('"', '', $title);
			$title = explode(PHP_EOL, $title)[0];
			$title = trim($title);

			$this->sessionMapper->updateSessionTitle($sessionId, $title);

			return new JSONResponse(['result' => $title]);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->warning('Failed to generate a title for the chat session', ['exception' => $e]);
			return new JSONResponse(['error' => $this->l10n->t('Failed to generate a title for the chat session')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
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

		$lastNMessages = intval($this->config->getAppValue(Application::APP_ID, 'chat_last_n_messages', '10'));
		$messages = $this->messageMapper->getMessages($sessionId, 0, $lastNMessages);

		if ($messages[0]->getRole() === 'system') {
			array_shift($messages);
		}
		$stichedPrompt .= implode(PHP_EOL, array_map(fn ($msg) => $msg->getContent(), $messages));

		return $stichedPrompt;
	}

	/**
	 * Synchrounous call to the LLM
	 *
	 * @param string $content
	 * @return string
	 */
	private function queryLLM(string $content): string {
		$task = new TextProcessingTask(FreePromptTaskType::class, $content, Application::APP_ID, $this->userId);
		return trim($this->textProcessingManager->runTask($task));
	}
}
