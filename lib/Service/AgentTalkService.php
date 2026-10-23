<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use DateTime;
use OCA\Assistant\Db\Agent;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\DB\Exception;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Connects Nextcloud Assistant chat sessions with Talk conversations.
 *
 * Talk integration uses OCA\Talk classes which are internal API.
 * The integration is disabled (this service does nothing) when Talk is not available.
 */
class AgentTalkService {

	public const CONFIRMATION_REFERENCE_PREFIX = 'assistant-conf:';
	public const REACTION_APPROVE = '👍';
	public const REACTION_REJECT = '👎';
	public const REACTION_READ = '👀';
	public const REACTION_DONE = '✅';
	public const REACTION_ERROR = '🔴';

	private const THREAD_TITLE_SEPARATOR = ' - ';

	public function __construct(
		private IUserManager $userManager,
		private AgentsService $agentsService,
		private ChatService $chatService,
		private SessionMapper $sessionMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Handle a Talk message that mentions an agent user
	 * or is a direct message in a 1-1 conversation with an agent user.
	 *
	 * @return void
	 */
	public function handleIncomingTalkMessage(
		\OCA\Talk\Room $room,
		IComment $comment,
	): void {
		if (!class_exists('OCA\Talk\Chat\ChatManager')) {
			return;
		}
		try {
			$authorActorType = $comment->getActorType();
			$authorActorId = $comment->getActorId();
			if ($authorActorType !== 'users') {
				return;
			}
			// Ignore messages sent by agent users to avoid loops
			if ($this->agentsService->getAgentByUserId($authorActorId) !== null) {
				return;
			}

			$agent = $this->getAgentTarget($room, $comment, $authorActorId);
			if ($agent === null) {
				return;
			}

			$humanUser = $this->userManager->get($authorActorId);
			if ($humanUser === null) {
				return;
			}

			$this->processAgentInteraction(
				$room,
				$agent,
				$humanUser,
				$comment,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to handle a Talk message for an agent', ['exception' => $e]);
		}
	}

	/**
	 * Find the agent user targeted by a Talk message:
	 * - in a 1-1 conversation: the other attendee, if it is a registered agent
	 * - in a group room: a mentioned registered agent that is a participant of the room.
	 * The agent must always answer in the private 1-1 conversation
	 * with the user who sent the message.
	 */
	private function getAgentTarget(
		\OCA\Talk\Room $room,
		IComment $comment,
		string $authorActorId,
	): ?Agent {
		if ($room->getType() === \OCA\Talk\Room::TYPE_ONE_TO_ONE) {
			$partnerUserId = $this->getOneToOnePartnerUserId($room, $authorActorId);
			if ($partnerUserId !== null) {
				return $this->agentsService->getAgentByUserId($partnerUserId);
			}
			return null;
		}

		// In a group room the mentioned agent must already be a participant of the room
		/** @var \OCA\Talk\Manager $talkManager */
		$talkManager = Server::get(\OCA\Talk\Manager::class);
		$mentions = $comment->getMentions();
		foreach ($mentions as $mention) {
			if ($mention['type'] !== 'user') {
				continue;
			}
			$mentionId = $mention['id'];
			if ($this->agentsService->getAgentByUserId($mentionId) !== null
				&& $talkManager->isUserAttendeeInRoom($mentionId, $room->getToken())) {
				return $this->agentsService->getAgentByUserId($mentionId);
			}
		}
		return null;
	}

	/**
	 * @return string|null The user id of the other actor in the room
	 */
	private function getOneToOnePartnerUserId(\OCA\Talk\Room $room, string $authorUserId): ?string {
		try {
			return $room->getSecondParticipant($authorUserId);
		} catch (\InvalidArgumentException|\JsonException|\OCA\Talk\Exceptions\RoomNotFoundException $e) {
			return null;
		}
	}

	/**
	 * @throws Exception
	 * @throws InternalException
	 * @throws UnauthorizedException
	 * @throws NotFoundException
	 * @throws BadRequestException
	 */
	private function processAgentInteraction(
		\OCA\Talk\Room $room,
		Agent $agent,
		IUser $humanUser,
		IComment $comment,
	): void {
		$humanUserId = $humanUser->getUID();

		// when the message is a reply inside the Talk thread of an agent session,
		// the generation happens in that session and the answer goes back to the same thread
		if ($room->getType() === \OCA\Talk\Room::TYPE_ONE_TO_ONE) {
			$triggerThreadId = (int)($comment->getTopmostParentId() ?? 0);
			if ($triggerThreadId !== 0) {
				try {
					$threadSession = $this->sessionMapper->getAgentSessionByThread($humanUserId, $triggerThreadId);
					$this->storeAndSchedule($threadSession, $comment, $humanUserId, $room, $agent);
					return;
				} catch (DoesNotExistException) {
					// the reply is not in the thread of an agent session: handle it below
				}
			}
		}

		// the 1-1 room the agent answers in
		$this->getOrCreateOneToOneRoom($humanUser, $agent->getUserId());

		// the name of the context is the origin room of the mention/message,
		// or the human display name when it was a direct 1-1 conversation
		$contextRoomName = ($room->getType() === \OCA\Talk\Room::TYPE_ONE_TO_ONE)
			? $humanUser->getDisplayName()
			: $room->getName();

		// find or create the assistant chat session dedicated to this agent/origin-room context
		$session = $this->getOrCreateAgentSession($humanUserId, $agent, $contextRoomName, $room->getToken());

		$this->storeAndSchedule($session, $comment, $humanUserId, $room, $agent);
	}

	/**
	 * @throws Exception
	 * @throws InternalException
	 * @throws UnauthorizedException
	 * @throws NotFoundException
	 * @throws BadRequestException
	 */
	private function storeAndSchedule(Session $session, IComment $comment, string $humanUserId, \OCA\Talk\Room $room, Agent $agent): void {
		// store the human Talk message in the chat session
		$creationTimestamp = $comment->getCreationDateTime()->getTimestamp();
		$talkContent = (string)$comment->getMessage();
		$this->chatService->createMessage(
			$humanUserId,
			(int)$session->getId(),
			Message::ROLE_HUMAN,
			$talkContent,
			$creationTimestamp,
			null,
		);

		// mark the message as seen before scheduling, so that the reaction
		// also happens when the generation cannot be scheduled right away
		// (the session answers one message at a time)
		$this->addAgentReaction($room, $agent->getUserId(), (int)$comment->getId(), self::REACTION_READ);
		// remember the seen message to later replace 👀 by ✅/🔴 after the generation
		$session->setAgentSeenRoomToken($room->getToken());
		$session->setAgentSeenCommentId((int)$comment->getId());
		try {
			$this->sessionMapper->update($session);
		} catch (Exception $e) {
			$this->logger->error('Failed to store the seen message of the agent session', ['exception' => $e]);
		}

		// schedule generation of the agent answer
		$this->chatService->scheduleMessageGeneration($humanUserId, (int)$session->getId());
	}

	/**
	 * The session is dedicated to one agent + human user + origin Talk room context:
	 * the Talk room in which the human mentioned the agent or wrote to him directly.
	 * All the agent answers are posted as one Talk thread of the 1-1 room
	 * between the human and the agent.
	 *
	 * @throws Exception
	 */
	private function getOrCreateAgentSession(string $humanUserId, Agent $agent, string $contextRoomName, string $originRoomToken): Session {
		try {
			return $this->sessionMapper->getAgentSession(
				$humanUserId, $agent->getUserId(), $originRoomToken
			);
		} catch (DoesNotExistException) {
			// continue with session creation
		} catch (Exception $e) {
			throw new InternalException($e->getMessage(), previous: $e);
		}

		$agentName = $this->getAgentDisplayName($agent);
		$sessionTitle = $agentName . self::THREAD_TITLE_SEPARATOR . $contextRoomName;

		$session = $this->chatService->createChatSession(
			$humanUserId,
			timestamp: null,
			title: $sessionTitle,
			systemPrompt: $agent->getPersonality(),
		);
		$session->setAgentUserId($agent->getUserId());
		$session->setRoomToken($originRoomToken);
		try {
			$this->sessionMapper->update($session);
		} catch (Exception $e) {
			throw new InternalException('Failed to store the agent context on the chat session', previous: $e);
		}
		return $session;
	}

	public function getAgentDisplayName(Agent $agent): string {
		$userId = $agent->getUserId();
		$user = $this->userManager->get($userId);
		$displayName = null;
		if ($user !== null) {
			$displayName = $user->getDisplayName();
		}
		return $displayName ?: $userId;
	}

	/**
	 * The 1-1 Talk room between a human user and an agent user
	 *
	 * @throws AgentUserException
	 */
	private function getOrCreateOneToOneRoom(IUser $humanUser, string $agentUserId): \OCA\Talk\Room {
		/** @var \OCA\Talk\Manager $talkManager */
		$talkManager = Server::get(\OCA\Talk\Manager::class);
		try {
			return $talkManager->getOne2OneRoom($humanUser->getUID(), $agentUserId);
		} catch (\OCA\Talk\Exceptions\RoomNotFoundException) {
			// fall through to the creation below
		}

		$agentUser = $this->userManager->get($agentUserId);
		if ($agentUser === null) {
			throw new AgentUserException('The agent user ' . $agentUserId . ' does not exist');
		}

		/** @var \OCA\Talk\Service\RoomService $talkRoomService */
		$talkRoomService = Server::get(\OCA\Talk\Service\RoomService::class);
		try {
			return $talkRoomService->createOneToOneConversation($humanUser, $agentUser);
		} catch (\OCA\Talk\Exceptions\RoomNotFoundException|\InvalidArgumentException $e) {
			throw new AgentUserException('Failed to create the 1-1 conversation with the agent user ' . $agentUserId, previous: $e);
		}
	}

	/**
	 * Called by ChattyLLMTaskListener instead of posting the agent answer
	 * in Talk: it will post the answer in the 1-1 Talk conversation thread.
	 *
	 * @param Session $session
	 * @param string $content The assistant message content
	 * @param string|null $pendingActions The pending sensitive actions (confirmation request) if any
	 * @return void
	 */
	public function postAgentTalkReply(
		Session $session,
		string $content,
		?string $pendingActions,
	): void {
		if ($session->getAgentUserId() === null) {
			return;
		}
		// only for agent sessions
		if (!class_exists('OCA\Talk\Chat\ChatManager')) {
			return;
		}
		$agentUser = $this->userManager->get($session->getAgentUserId());
		if ($agentUser === null) {
			$this->logger->warning('The agent user was deleted while an agent answer was being handled');
			return;
		}
		$humanUser = $this->userManager->get($session->getUserId());
		if ($humanUser === null) {
			$this->logger->warning('The session owner user was deleted while an agent answer was being handled');
			return;
		}
		try {
			$room = $this->getOrCreateOneToOneRoom($humanUser, $agentUser->getUID());
		} catch (AgentUserException|\OCA\Talk\Exceptions\RoomNotFoundException $e) {
			$this->logger->warning('Could not get the 1-1 Talk room to post an agent reply', [
				'agentuserId' => $agentUser->getUID(),
				'humanUserId' => $humanUser->getUID(),
				'exception' => $e,
			]);
			return;
		}

		// messages sent outside a thread of a 1-1 conversation are answered
		// with plain 1-1 messages; answers to a thread conversation go back in the thread
		$threaded = true;
		if ($session->getRoomToken() !== null) {
			try {
				$originRoom = $this->getRoomByToken($session->getRoomToken());
				$threaded = $originRoom->getType() !== \OCA\Talk\Room::TYPE_ONE_TO_ONE;
			} catch (\OCA\Talk\Exceptions\RoomNotFoundException) {
				// the origin room may have been deleted, still answer threaded
			}
		}

		$posted = $this->postAgentMessage(
			$room,
			$session,
			$content,
			referenceId: null,
			threaded: $threaded,
		);

		$confirmPosted = false;
		if ($pendingActions !== null && trim($pendingActions) !== '') {
			$confirmPosted = $this->postAgentMessage(
				$room,
				$session,
				$pendingActions,
				referenceId: self::CONFIRMATION_REFERENCE_PREFIX . $session->getId() . ':' . time(),
				addApproveRejectReactions: true,
				threaded: $threaded,
			);
		}

		// an agency confirmation turn has no reply content, only the confirmation message
		$this->markGenerationCompleted($session, $posted || $confirmPosted);
	}

	/**
	 * Called by a listener when a human user reacts in Talk to a confirmation message
	 * (sensitive actions of the agency feature).
	 *
	 * The agent removes its own 👍/👎 (only the user's reaction remains),
	 * marks the message with 👀 as seen, and does the same as when confirming
	 * or rejecting from the assistant UI.
	 *
	 * @param string $humanUserId
	 * @param int $sessionId
	 * @param int $messageId The id of the Talk message that was reacted to
	 * @param string $reaction
	 * @return void
	 */
	public function handleConfirmationReaction(string $humanUserId, int $sessionId, int $messageId, string $reaction, \OCA\Talk\Room $room): void {
		if (!in_array($reaction, [self::REACTION_APPROVE, self::REACTION_REJECT], true)) {
			return;
		}
		try {
			$session = $this->sessionMapper->getUserSession($humanUserId, $sessionId);
		} catch (DoesNotExistException|Exception $e) {
			$this->logger->warning('Could not find the session matching the Talk reaction', [
				'humanUserId' => $humanUserId,
				'sessionId' => $sessionId,
			]);
			return;
		}
		if ($session->getAgentUserId() === null) {
			return;
		}

		// remove the agent's own thumbs so only the one of the user remains,
		// then mark the confirmation as being processed
		$this->deleteAgentReaction($room, $session->getAgentUserId(), $messageId, self::REACTION_APPROVE);
		$this->deleteAgentReaction($room, $session->getAgentUserId(), $messageId, self::REACTION_REJECT);
		$this->addAgentReaction($room, $session->getAgentUserId(), $messageId, self::REACTION_READ);
		// remember the seen message to later replace 👀 by ✅/🔴 after the confirmation run
		$session->setAgentSeenRoomToken($room->getToken());
		$session->setAgentSeenCommentId($messageId);
		try {
			$this->sessionMapper->update($session);
		} catch (Exception $e) {
			$this->logger->error('Failed to store the seen confirmation of the agent session', ['exception' => $e]);
		}
		try {
			$this->chatService->scheduleMessageGeneration(
				$humanUserId,
				$sessionId,
				$reaction === self::REACTION_APPROVE ? 1 : 0,
			);
		} catch (InternalException|NotFoundException|BadRequestException|UnauthorizedException $e) {
			$this->logger->error('Failed to schedule the confirmation follow-up after a Talk reaction', ['exception' => $e]);
		}
	}

	private function postAgentMessage(
		\OCA\Talk\Room $room,
		Session $session,
		string $content,
		?string $referenceId = null,
		bool $addApproveRejectReactions = false,
		bool $threaded = true,
	): bool {
		if (trim($content) === '') {
			return false;
		}
		$agentUser = $this->userManager->get($session->getAgentUserId());
		if ($agentUser === null) {
			return false;
		}
		/** @var \OCA\Talk\Chat\ChatManager $chatManager */
		$chatManager = Server::get(\OCA\Talk\Chat\ChatManager::class);

		$threadId = \OCA\Talk\Model\Thread::THREAD_NONE;
		$threadTitle = '';
		if ($threaded) {
			if ($session->getThreadId() !== null) {
				// answer in the existing thread of the session
				$threadId = $session->getThreadId();
			} else {
				// create the thread of the "real chat session"
				$threadId = \OCA\Talk\Model\Thread::THREAD_CREATE;
				$threadTitle = $session->getTitle() ?? $this->getAgentDisplayName($this->getAgentByUserIdOrFail($session));
			}
		}

		try {
			$comment = $chatManager->sendMessage(
				$room,
				null,
				'users',
				$session->getAgentUserId(),
				$content,
				new DateTime(),
				replyTo: null,
				referenceId: $referenceId ?? '',
				threadId: $threadId,
				threadTitle: $threadTitle,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to post an agent message in Talk', ['exception' => $e]);
			return false;
		}

		if ($threadId === \OCA\Talk\Model\Thread::THREAD_CREATE) {
			// ChatManager only sets the thread metadata, the thread and the thread_created
			// system message are created by the caller, like Talk's ChatController does
			/** @var \OCA\Talk\Service\ThreadService $threadService */
			$threadService = Server::get(\OCA\Talk\Service\ThreadService::class);
			try {
				$thread = $threadService->createThread($room, (int)$comment->getId(), $threadTitle);
			} catch (\Throwable $e) {
				$this->logger->error('Failed to create the Talk thread of the agent session', ['exception' => $e]);
				return false;
			}
			try {
				$chatManager->addSystemMessage(
					$room,
					null,
					'users',
					$session->getAgentUserId(),
					json_encode([
						'message' => 'thread_created',
						'parameters' => [
							'thread' => (int)$comment->getId(),
							'title' => $thread->getName(),
						],
					]),
					new DateTime(),
					sendNotifications: false,
					replyTo: $comment,
					shouldSkipLastMessageUpdate: true,
				);
			} catch (\Throwable $e) {
				$this->logger->warning('Failed to post the thread_created system message', ['exception' => $e]);
			}
		}

		if ($threaded) {
			// remember the thread, so the next messages are posted in it
			$threadId = (int)($comment->getTopmostParentId() ?: $comment->getId());
			if ($session->getThreadId() !== $threadId) {
				$session->setThreadId($threadId);
				try {
					$this->sessionMapper->update($session);
				} catch (Exception $e) {
					$this->logger->error('Failed to store the Talk thread of the agent session', ['exception' => $e]);
				}
			}
		}

		if ($addApproveRejectReactions) {
			$this->addAgentReaction($room, $session->getAgentUserId(), (int)$comment->getId(), self::REACTION_APPROVE);
			$this->addAgentReaction($room, $session->getAgentUserId(), (int)$comment->getId(), self::REACTION_REJECT);
		}
		return true;
	}

	/**
	 * Replace the 👀 reaction of the agent on the last seen human message
	 * by the outcome emoji (✅ answer generated, 🔴 generation failed).
	 */
	private function updateSeenReaction(Session $session, string $reaction): void {
		$seenRoomToken = $session->getAgentSeenRoomToken();
		$seenCommentId = $session->getAgentSeenCommentId();
		if ($seenRoomToken === null || $seenCommentId === null) {
			return;
		}
		try {
			$room = $this->getRoomByToken($seenRoomToken);
		} catch (\OCA\Talk\Exceptions\RoomNotFoundException $e) {
			$this->logger->warning('Cannot update the reaction, the Talk room of the seen message was not found', ['token' => $seenRoomToken]);
			return;
		}
		$this->deleteAgentReaction($room, $session->getAgentUserId(), $seenCommentId, self::REACTION_READ);
		$this->addAgentReaction($room, $session->getAgentUserId(), $seenCommentId, $reaction);
		// forget the seen message
		$session->setAgentSeenRoomToken(null);
		$session->setAgentSeenCommentId(null);
		try {
			$this->sessionMapper->update($session);
		} catch (Exception $e) {
			$this->logger->error('Failed to forget the seen message of the agent session', ['exception' => $e]);
		}
	}

	/**
	 * Called by ChattyLLMTaskListener once the answer of an agent session was generated
	 *
	 * @param Session $session
	 * @param bool $postedInTalk Whether the answer was successfully posted in Talk
	 * @return void
	 */
	public function markGenerationCompleted(Session $session, bool $postedInTalk): void {
		if ($session->getAgentUserId() === null) {
			return;
		}
		if ($postedInTalk) {
			$this->updateSeenReaction($session, self::REACTION_DONE);
		} else {
			$this->updateSeenReaction($session, self::REACTION_ERROR);
		}
	}

	/**
	 * Called by ChattyLLMTaskListener when the generation task of an agent session failed
	 *
	 * @param string $humanUserId
	 * @param int $sessionId
	 * @return void
	 */
	public function markGenerationFailed(string $humanUserId, int $sessionId): void {
		try {
			$session = $this->sessionMapper->getUserSession($humanUserId, $sessionId);
		} catch (DoesNotExistException|Exception $e) {
			$this->logger->warning('Cannot find the session of a failed agent message generation', ['sessionId' => $sessionId]);
			return;
		}
		if ($session->getAgentUserId() === null) {
			return;
		}
		$this->updateSeenReaction($session, self::REACTION_ERROR);
	}

	private function deleteAgentReaction(\OCA\Talk\Room $room, string $agentUserId, int $messageId, string $reaction): void {
		if (trim($reaction) === '' || $this->userManager->get($agentUserId) === null) {
			return;
		}
		try {
			/** @var \OCA\Talk\Chat\ReactionManager $reactionManager */
			$reactionManager = Server::get(\OCA\Talk\Chat\ReactionManager::class);
			$reactionManager->deleteReactionMessage(
				$room,
				'users',
				$agentUserId,
				$agentUserId,
				$messageId,
				$reaction,
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to remove an automatic reaction on an agent message in Talk', ['exception' => $e]);
		}
	}

	private function addAgentReaction(\OCA\Talk\Room $room, string $agentUserId, int $messageId, string $reaction): void {
		$agentUser = $this->userManager->get($agentUserId);
		if ($agentUser === null) {
			return;
		}
		try {
			/** @var \OCA\Talk\Chat\ReactionManager $reactionManager */
			$reactionManager = Server::get(\OCA\Talk\Chat\ReactionManager::class);
			$reactionManager->addReactionMessage(
				$room,
				'users',
				$agentUserId,
				$agentUser->getDisplayName() ?: $agentUserId,
				$messageId,
				$reaction,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to add an automatic reaction on an agent message in Talk', ['exception' => $e]);
		}
	}

	private function getRoomByToken(string $token): \OCA\Talk\Room {
		/** @var \OCA\Talk\Manager $talkManager */
		$talkManager = Server::get(\OCA\Talk\Manager::class);
		return $talkManager->getRoomByToken($token);
	}

	private function getAgentByUserIdOrFail(Session $session): Agent {
		$agent = $this->agentsService->getAgentByUserId($session->getAgentUserId());
		if ($agent === null) {
			throw new AgentUserException('Agent user ' . $session->getAgentUserId() . ' not found');
		}
		return $agent;
	}

}
