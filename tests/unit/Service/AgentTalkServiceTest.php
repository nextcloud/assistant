<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AgencyActionService;
use OCA\Assistant\Service\AgentsService;
use OCA\Assistant\Service\AgentTalkFileService;
use OCA\Assistant\Service\AgentTalkService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * The automatic reactions of the agent are not asserted here: they go through
 * the Talk ReactionManager, which is only available when Talk is installed.
 * The reaction bookkeeping is verified through the session state instead.
 */
class AgentTalkServiceTest extends TestCase {

	private const AGENT_USER_ID = 'agent_user';
	private const HUMAN_USER_ID = 'human_user';
	private const SESSION_ID = 42;
	private const ROOM_TOKEN = 'room-token';
	private const COMMENT_ID = 1234;

	private AgentTalkService $service;
	private ChatService&MockObject $chatService;
	private SessionMapper&MockObject $sessionMapper;
	private Session $session;

	protected function setUp(): void {
		parent::setUp();

		if (!class_exists('OCA\Talk\Room')) {
			$this->markTestSkipped('Talk is not installed');
		}

		$this->session = new Session();
		$this->session->setId(self::SESSION_ID);
		$this->session->setUserId(self::AGENT_USER_ID);
		$this->session->setRequesterUserId(self::HUMAN_USER_ID);
		$this->session->setAgentSeenRoomToken(self::ROOM_TOKEN);
		$this->session->setAgentSeenCommentId(self::COMMENT_ID);

		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->sessionMapper->method('getAgentSessionBySeenMessage')->willReturn($this->session);

		$this->chatService = $this->createMock(ChatService::class);

		$this->service = new AgentTalkService(
			$this->createMock(IUserManager::class),
			$this->createMock(AgentsService::class),
			$this->chatService,
			$this->sessionMapper,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IL10N::class),
			$this->createMock(AgencyActionService::class),
			$this->createMock(AgentTalkFileService::class),
			$this->createMock(TaskProcessingService::class),
			$this->createMock(LoggerInterface::class),
		);
	}

	private function getRoom(): \OCA\Talk\Room {
		/** @var \OCA\Talk\Room&MockObject $room */
		$room = $this->createMock(\OCA\Talk\Room::class);
		$room->method('getToken')->willReturn(self::ROOM_TOKEN);
		return $room;
	}

	private function getComment(): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn(self::COMMENT_ID);
		return $comment;
	}

	/**
	 * A ⏹️ reaction of the requester on the message being handled stops the
	 * generation: the pending task is cancelled, the 👀 is forgotten so no
	 * late outcome can be applied to it.
	 */
	public function testStopReactionCancelsTheRunningGeneration(): void {
		$this->chatService->method('cancelPendingGenerationTasks')
			->with(self::AGENT_USER_ID, self::SESSION_ID)
			->willReturn(2);
		$this->sessionMapper->expects($this->once())->method('update');

		$this->service->handleStopReaction(self::HUMAN_USER_ID, $this->getRoom(), $this->getComment());

		$this->assertNull($this->session->getAgentSeenRoomToken());
		$this->assertNull($this->session->getAgentSeenCommentId());
	}

	/**
	 * When nothing is scheduled or running, the stop reaction is ignored so
	 * that a generation that just completed can still replace 👀 by its outcome.
	 */
	public function testStopReactionIsIgnoredWhenNothingIsCancelled(): void {
		$this->chatService->method('cancelPendingGenerationTasks')->willReturn(0);
		$this->sessionMapper->expects($this->never())->method('update');

		$this->service->handleStopReaction(self::HUMAN_USER_ID, $this->getRoom(), $this->getComment());

		$this->assertSame(self::ROOM_TOKEN, $this->session->getAgentSeenRoomToken());
		$this->assertSame(self::COMMENT_ID, $this->session->getAgentSeenCommentId());
	}

	/**
	 * Only the human user the agent session is dedicated to may stop it:
	 * other members of a group room must not be able to cancel a generation.
	 */
	public function testStopReactionByAnotherUserIsIgnored(): void {
		$this->chatService->expects($this->never())->method('cancelPendingGenerationTasks');

		$this->service->handleStopReaction('another_user', $this->getRoom(), $this->getComment());

		$this->assertSame(self::ROOM_TOKEN, $this->session->getAgentSeenRoomToken());
	}

	public function testStopReactionOnUnhandledMessageIsIgnored(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->method('getAgentSessionBySeenMessage')
			->willThrowException(new DoesNotExistException('no session handles this message'));
		$this->chatService->expects($this->never())->method('cancelPendingGenerationTasks');

		$service = new AgentTalkService(
			$this->createMock(IUserManager::class),
			$this->createMock(AgentsService::class),
			$this->chatService,
			$sessionMapper,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IL10N::class),
			$this->createMock(AgencyActionService::class),
			$this->createMock(AgentTalkFileService::class),
			$this->createMock(TaskProcessingService::class),
			$this->createMock(LoggerInterface::class),
		);

		$service->handleStopReaction(self::HUMAN_USER_ID, $this->getRoom(), $this->getComment());
	}
}
