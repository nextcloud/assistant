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
use OCP\IL10N;
use OCP\IURLGenerator;
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
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private AgencyActionService $agencyActionService,
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

			if (!$this->agentsService->canUserAccessAgent($agent, $humanUser)) {
				$this->handleAgentAccessDenied($agent, $humanUser, $comment, $room);
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
	 * The user does not have access to the agent: no session is created and no
	 * answer is generated. A short notice is sent as the agent in the private
	 * 1-1 conversation, as a reply to the triggering message when it was
	 * written in that 1-1 conversation (or one of its threads).
	 *
	 * The reaction lifecycle of the handled message and the pointer message
	 * posted in a group room are identical to the answer flow, so that room
	 * members cannot infer who has access to the agent.
	 */
	private function handleAgentAccessDenied(Agent $agent, IUser $humanUser, IComment $comment, \OCA\Talk\Room $originRoom): void {
		if (!class_exists('OCA\Talk\Chat\ChatManager')) {
			return;
		}
		$agentUserId = $agent->getUserId();
		$messageId = (int)$comment->getId();

		// like the answer flow: 👀 while being handled, ✅ once the notice is posted
		$this->addAgentReaction($originRoom, $agentUserId, $messageId, self::REACTION_READ);
		try {
			$oneToOneRoom = $this->getOrCreateOneToOneRoom($humanUser, $agentUserId);
		} catch (AgentUserException|\OCA\Talk\Exceptions\RoomNotFoundException $e) {
			$this->logger->warning('Could not get the 1-1 Talk room to post the access notice', [
				'agentUserId' => $agentUserId,
				'humanUserId' => $humanUser->getUID(),
				'exception' => $e,
			]);
			$this->deleteAgentReaction($originRoom, $agentUserId, $messageId, self::REACTION_READ);
			$this->addAgentReaction($originRoom, $agentUserId, $messageId, self::REACTION_ERROR);
			return;
		}

		$content = $this->l10n->t(
			'You do not have access to the agent "%s". Please contact your administrator if you need access.',
			[$this->getAgentDisplayName($agent)]
		);
		try {
			/** @var \OCA\Talk\Chat\ChatManager $chatManager */
			$chatManager = Server::get(\OCA\Talk\Chat\ChatManager::class);
			$handledInOneToOne = ($comment->getObjectType() === 'chat'
				&& $comment->getObjectId() === (string)$oneToOneRoom->getId());
			$noticeComment = $chatManager->sendMessage(
				$oneToOneRoom,
				null,
				'users',
				$agentUserId,
				$content,
				new DateTime(),
				replyTo: $handledInOneToOne ? $comment : null,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to post the agent access notice in Talk', ['exception' => $e]);
			$this->deleteAgentReaction($originRoom, $agentUserId, $messageId, self::REACTION_READ);
			$this->addAgentReaction($originRoom, $agentUserId, $messageId, self::REACTION_ERROR);
			return;
		}

		$this->deleteAgentReaction($originRoom, $agentUserId, $messageId, self::REACTION_READ);
		$this->addAgentReaction($originRoom, $agentUserId, $messageId, self::REACTION_DONE);

		if ($originRoom->getType() !== \OCA\Talk\Room::TYPE_ONE_TO_ONE) {
			// the mention happened in a group room: post the same pointer
			// message as the answer flow, so that room members cannot infer
			// who has access to the agent
			try {
				$this->postRoomPointer($originRoom, $comment, $oneToOneRoom, $noticeComment, $agentUserId);
			} catch (\Throwable $e) {
				$this->logger->error('Failed to post the access notice pointer message in the origin Talk room', ['exception' => $e]);
			}
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
					$threadSession = $this->sessionMapper->getAgentSessionByThread($agent->getUserId(), $triggerThreadId);
					$this->storeAndSchedule($threadSession, $comment, $room, $agent);
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

		$this->storeAndSchedule($session, $comment, $room, $agent);
	}

	/**
	 * @throws Exception
	 * @throws InternalException
	 * @throws UnauthorizedException
	 * @throws NotFoundException
	 * @throws BadRequestException
	 */
	private function storeAndSchedule(Session $session, IComment $comment, \OCA\Talk\Room $room, Agent $agent): void {
		// a new human message implicitly answers a pending confirmation
		// like a rejection would: remove the now meaningless 👍/👎 reactions
		// and the pending actions, then generate a new answer
		$this->removePendingConfirmationReactions($session);
		$session->setAgencyPendingActions(null);

		// store the human Talk message in the chat session
		$creationTimestamp = $comment->getCreationDateTime()->getTimestamp();
		$talkContent = (string)$comment->getMessage();
		$this->chatService->createMessage(
			$session->getUserId(),
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
		$this->chatService->scheduleMessageGeneration($session->getUserId(), (int)$session->getId(), 0, $this->getOriginRoomInfo($session));
	}

	/**
	 * The session is dedicated to one agent + human user + origin Talk room context:
	 * the Talk room in which the human mentioned the agent or wrote to him directly.
	 * All the agent answers are posted as one Talk thread of the 1-1 room
	 * between the human and the agent.
	 *
	 * Agent sessions are owned by the agent user account,
	 * the human user is recorded as the requester.
	 *
	 * @throws Exception
	 */
	private function getOrCreateAgentSession(string $humanUserId, Agent $agent, string $contextRoomName, string $originRoomToken): Session {
		try {
			return $this->sessionMapper->getAgentSession(
				$agent->getUserId(), $humanUserId, $originRoomToken
			);
		} catch (DoesNotExistException) {
			// continue with session creation
		} catch (Exception $e) {
			throw new InternalException($e->getMessage(), previous: $e);
		}

		$agentName = $this->getAgentDisplayName($agent);
		$sessionTitle = $agentName . self::THREAD_TITLE_SEPARATOR . $contextRoomName;

		$session = $this->chatService->createChatSession(
			$agent->getUserId(),
			timestamp: null,
			title: $sessionTitle,
			systemPrompt: $agent->getPersonality(),
			requesterUserId: $humanUserId,
		);
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
		if ($session->getRequesterUserId() === null) {
			return;
		}
		// only for agent sessions
		if (!class_exists('OCA\Talk\Chat\ChatManager')) {
			return;
		}
		$agentUser = $this->userManager->get($session->getUserId());
		if ($agentUser === null) {
			$this->logger->warning('The agent user was deleted while an agent answer was being handled');
			return;
		}
		$humanUser = $this->userManager->get($session->getRequesterUserId());
		if ($humanUser === null) {
			$this->logger->warning('The requester user was deleted while an agent answer was being handled');
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

		$postedComment = $this->postAgentMessage(
			$room,
			$session,
			$content,
			referenceId: null,
			threaded: $threaded,
		);

		$confirmPosted = false;
		if ($pendingActions !== null && trim($pendingActions) !== '') {
			// a new confirmation supersedes a previous unanswered one:
			// remove the 👍/👎 reactions of the superseded confirmation message
			$this->removePendingConfirmationReactions($session);
			// the confirmation request is posted as natural language;
			// fall back to the raw actions JSON if it cannot be formatted
			$confirmationContent = $this->agencyActionService->formatConfirmationMessage($pendingActions) ?? $pendingActions;
			$confirmationComment = $this->postAgentMessage(
				$room,
				$session,
				$confirmationContent,
				referenceId: self::CONFIRMATION_REFERENCE_PREFIX . $session->getId() . ':' . time(),
				addApproveRejectReactions: true,
				threaded: $threaded,
			);
			if ($confirmationComment !== null) {
				$confirmPosted = true;
				// remember the confirmation message to remove the 👍/👎 reactions
				// if the user sends a new message instead of reacting
				$session->setAgencyConfirmationRoomToken($room->getToken());
				$session->setAgencyConfirmationCommentId((int)$confirmationComment->getId());
				try {
					$this->sessionMapper->update($session);
				} catch (Exception $e) {
					$this->logger->error('Failed to store the pending confirmation of the agent session', ['exception' => $e]);
				}
			}
		}

		if ($postedComment !== null) {
			try {
				$this->postGroupRoomPointer($session, $room, $postedComment);
			} catch (\Throwable $e) {
				$this->logger->error('Failed to post the answer pointer message in the origin Talk room', ['exception' => $e]);
			}
		}

		// an agency confirmation turn has no reply content, only the confirmation message
		$this->markGenerationCompleted($session, $postedComment !== null || $confirmPosted);
	}

	/**
	 * When the answer was requested by an @mention from a group Talk room,
	 * post a short pointer message there, as the agent, in reply to the mention:
	 * it mentions the human user and links to the answer posted
	 * in the private 1-1 conversation with the agent.
	 *
	 * Messages sent in the 1-1 conversation or in its threads do not trigger
	 * a pointer: their answers are posted in that same conversation.
	 */
	private function postGroupRoomPointer(Session $session, \OCA\Talk\Room $oneToOneRoom, IComment $answerComment): void {
		$seenRoomToken = $session->getAgentSeenRoomToken();
		$seenCommentId = $session->getAgentSeenCommentId();
		if ($seenRoomToken === null || $seenCommentId === null) {
			return;
		}
		try {
			$seenRoom = $this->getRoomByToken($seenRoomToken);
		} catch (\OCA\Talk\Exceptions\RoomNotFoundException $e) {
			$this->logger->warning('Cannot post the answer pointer message, the Talk room of the mention was not found', ['token' => $seenRoomToken]);
			return;
		}
		if ($seenRoom->getType() === \OCA\Talk\Room::TYPE_ONE_TO_ONE) {
			// the message was sent in the 1-1 conversation or one of its threads,
			// the answer was posted there: no pointer message needed
			return;
		}
		$requesterUserId = $session->getRequesterUserId();
		if ($requesterUserId === null) {
			return;
		}

		// the mention message to reply to must be a chat message of the handled room
		try {
			/** @var \OCA\Talk\Chat\CommentsManager $commentsManager */
			$commentsManager = Server::get(\OCA\Talk\Chat\CommentsManager::class);
			$mentionComment = $commentsManager->getCommentsById([(string)$seenCommentId])[(string)$seenCommentId] ?? null;
		} catch (\Throwable $e) {
			$this->logger->warning('Cannot post the answer pointer message, the handled Talk message was not found', ['commentId' => $seenCommentId]);
			return;
		}
		if ($mentionComment === null) {
			return;
		}
		if ($mentionComment->getActorId() !== $requesterUserId) {
			return;
		}

		$threadId = (int)($answerComment->getMetaData()[\OCA\Talk\Model\Message::METADATA_THREAD_ID] ?? 0);
		$this->postRoomPointer($seenRoom, $mentionComment, $oneToOneRoom, $answerComment, $session->getUserId(), $threadId !== 0 ? $threadId : null);
	}

	/**
	 * Post a pointer message in the origin Talk room, as the agent, in reply to
	 * the human's message: it mentions the human user and links, inside the
	 * sentence, to the message posted in the private 1-1 conversation with
	 * the agent.
	 *
	 * The message is identical whether the agent answered or refused access,
	 * so that room members cannot infer who has access to the agent.
	 */
	private function postRoomPointer(
		\OCA\Talk\Room $originRoom,
		IComment $mentionComment,
		\OCA\Talk\Room $oneToOneRoom,
		IComment $answerComment,
		string $agentUserId,
		?int $threadId = null,
	): void {
		if ($mentionComment->getObjectType() !== 'chat'
			|| $mentionComment->getObjectId() !== (string)$originRoom->getId()
			|| $mentionComment->getActorType() !== 'users') {
			return;
		}

		$urlParams = [
			'token' => $oneToOneRoom->getToken(),
			'_fragment' => 'message_' . $answerComment->getId(),
		];
		if ($threadId !== null) {
			// like Talk's notification links: thread context is needed to open the message
			$urlParams['threadId'] = $threadId;
		}
		$link = $this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', $urlParams);
		$content = '@"' . $mentionComment->getActorId() . '" '
			. $this->l10n->t('I answered to you in [our private conversation](%s)', [$link]);

		/** @var \OCA\Talk\Chat\ChatManager $chatManager */
		$chatManager = Server::get(\OCA\Talk\Chat\ChatManager::class);
		$chatManager->sendMessage(
			$originRoom,
			null,
			'users',
			$agentUserId,
			$content,
			new DateTime(),
			replyTo: $mentionComment,
		);
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
			$session = $this->sessionMapper->getSessionById($sessionId);
		} catch (DoesNotExistException|Exception $e) {
			$this->logger->warning('Could not find the session matching the Talk reaction', [
				'humanUserId' => $humanUserId,
				'sessionId' => $sessionId,
			]);
			return;
		}
		if ($session->getRequesterUserId() === null) {
			// the session is not an agent session
			return;
		}
		if ($session->getRequesterUserId() !== $humanUserId) {
			// the reaction was not added by the human user the agent session is dedicated to
			return;
		}

		// remove the agent's own thumbs so only the one of the user remains,
		// then mark the confirmation as being processed
		$this->deleteAgentReaction($room, $session->getUserId(), $messageId, self::REACTION_APPROVE);
		$this->deleteAgentReaction($room, $session->getUserId(), $messageId, self::REACTION_REJECT);
		$this->addAgentReaction($room, $session->getUserId(), $messageId, self::REACTION_READ);
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
				$session->getUserId(),
				$sessionId,
				$reaction === self::REACTION_APPROVE ? 1 : 0,
				$this->getOriginRoomInfo($session),
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
	): ?IComment {
		if (trim($content) === '') {
			return null;
		}
		$agentUser = $this->userManager->get($session->getUserId());
		if ($agentUser === null) {
			return null;
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
				$session->getUserId(),
				$content,
				new DateTime(),
				replyTo: null,
				referenceId: $referenceId ?? '',
				threadId: $threadId,
				threadTitle: $threadTitle,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to post an agent message in Talk', ['exception' => $e]);
			return null;
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
				return null;
			}
			try {
				$chatManager->addSystemMessage(
					$room,
					null,
					'users',
					$session->getUserId(),
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
			$this->addAgentReaction($room, $session->getUserId(), (int)$comment->getId(), self::REACTION_APPROVE);
			$this->addAgentReaction($room, $session->getUserId(), (int)$comment->getId(), self::REACTION_REJECT);
		}
		return $comment;
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
		$this->deleteAgentReaction($room, $session->getUserId(), $seenCommentId, self::REACTION_READ);
		$this->addAgentReaction($room, $session->getUserId(), $seenCommentId, $reaction);
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
		if ($session->getUserId() === null) {
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
	 * @param string $sessionOwnerUserId The agent user id owning the session
	 * @param int $sessionId
	 * @return void
	 */
	public function markGenerationFailed(string $sessionOwnerUserId, int $sessionId): void {
		try {
			$session = $this->sessionMapper->getUserSession($sessionOwnerUserId, $sessionId);
		} catch (DoesNotExistException|Exception $e) {
			$this->logger->warning('Cannot find the session of a failed agent message generation', ['sessionId' => $sessionId]);
			return;
		}
		if ($session->getRequesterUserId() === null) {
			return;
		}
		$this->updateSeenReaction($session, self::REACTION_ERROR);
	}

	/**
	 * A pending agency confirmation was implicitly answered
	 * (a new human message was sent, or a new confirmation supersedes it):
	 * remove the agent's own 👍/👎 reactions on the pending confirmation
	 * message and clear the confirmation tracking.
	 *
	 * The session caller is responsible for persisting the cleared fields.
	 */
	private function removePendingConfirmationReactions(Session $session): void {
		$commentId = $session->getAgencyConfirmationCommentId();
		$roomToken = $session->getAgencyConfirmationRoomToken();
		if ($commentId === null || $roomToken === null) {
			return;
		}
		$session->setAgencyConfirmationRoomToken(null);
		$session->setAgencyConfirmationCommentId(null);
		try {
			$room = $this->getRoomByToken($roomToken);
		} catch (\OCA\Talk\Exceptions\RoomNotFoundException) {
			// the room was deleted, the reactions are gone with it
			return;
		}
		$this->deleteAgentReaction($room, $session->getUserId(), $commentId, self::REACTION_APPROVE);
		$this->deleteAgentReaction($room, $session->getUserId(), $commentId, self::REACTION_REJECT);
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

	/**
	 * The name of the Talk room where the conversation of an agent session
	 * started, when the origin room is a group room. The identity context of
	 * the generation tasks mentions it, no other task attribute does.
	 *
	 * @param Session $session
	 * @return string|null
	 */
	private function getOriginRoomInfo(Session $session): ?string {
		if ($session->getRoomToken() === null) {
			return null;
		}
		try {
			$originRoom = $this->getRoomByToken($session->getRoomToken());
		} catch (\OCA\Talk\Exceptions\RoomNotFoundException) {
			return null;
		}
		if ($originRoom->getType() === \OCA\Talk\Room::TYPE_ONE_TO_ONE) {
			// 1-1 conversations need no origin mention, everything happens there
			return null;
		}
		return $originRoom->getName();
	}

	private function getAgentByUserIdOrFail(Session $session): Agent {
		$agent = $this->agentsService->getAgentByUserId($session->getUserId());
		if ($agent === null) {
			throw new AgentUserException('Agent user ' . $session->getUserId() . ' not found');
		}
		return $agent;
	}

}
