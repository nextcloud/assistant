<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\UnauthorizedException;
use OCP\IUserManager;
use OCP\Server;
use Test\TestCase;

/**
 * Integration tests for the ChatService chat session deletion methods.
 *
 * Runs against a real Nextcloud test server with a real database.
 * Creates dedicated test users and cleans up all chat data in tearDown.
 *
 * @group DB
 */
class ChatServiceTest extends TestCase {

	private const TEST_USER = 'chat_service_test_user';
	private const OTHER_USER = 'chat_service_other_user';

	private ChatService $service;
	private SessionMapper $sessionMapper;
	private MessageMapper $messageMapper;
	/** @var list<string> user IDs created by this test, deleted again in tearDown */
	private array $createdUsers = [];
	/** @var array<string, list<int>> session IDs created by this test, per user ID */
	private array $createdSessionIds = [];

	protected function setUp(): void {
		parent::setUp();

		/** @var IUserManager $userManager */
		$userManager = Server::get(IUserManager::class);
		foreach ([self::TEST_USER, self::OTHER_USER] as $uid) {
			if (!$userManager->userExists($uid)) {
				$userManager->createUser($uid, $uid . '_password123');
				$this->createdUsers[] = $uid;
			}
		}

		$this->service = Server::get(ChatService::class);
		$this->sessionMapper = Server::get(SessionMapper::class);
		$this->messageMapper = Server::get(MessageMapper::class);
	}

	protected function tearDown(): void {
		// only remove what this test created, the instance is not always in a clean state in CI
		foreach ($this->createdSessionIds as $uid => $sessionIds) {
			foreach ($sessionIds as $sessionId) {
				$this->sessionMapper->deleteSession($uid, $sessionId);
				$this->messageMapper->deleteMessagesBySession($sessionId);
			}
		}
		$userManager = Server::get(IUserManager::class);
		foreach ($this->createdUsers as $uid) {
			if ($userManager->userExists($uid)) {
				$this->service->deleteAllUserChatData($uid);
				$userManager->get($uid)->delete();
			}
		}
		parent::tearDown();
	}

	private function createSession(string $userId): Session {
		$session = new Session();
		$session->setUserId($userId);
		$session->setTimestamp(time());
		$session = $this->sessionMapper->insert($session);
		$this->createdSessionIds[$userId][] = $session->getId();
		return $session;
	}

	private function createMessage(int $sessionId): Message {
		$message = new Message();
		$message->setSessionId($sessionId);
		$message->setRole(Message::ROLE_HUMAN);
		$message->setAttachments('[]');
		$message->setContent('test message');
		$message->setSources('[]');
		$message->setTimestamp(time());
		return $this->messageMapper->insert($message);
	}

	public function testDeleteSessions(): void {
		$session1 = $this->createSession(self::TEST_USER);
		$session2 = $this->createSession(self::TEST_USER);
		$session3 = $this->createSession(self::TEST_USER);
		$this->createMessage($session1->getId());
		$this->createMessage($session2->getId());
		$this->createMessage($session3->getId());

		$this->service->deleteSessions(self::TEST_USER, [$session1->getId(), $session2->getId(), $session3->getId()]);

		$remainingIds = array_map(static function (Session $session) {
			return $session->getId();
		}, $this->sessionMapper->getUserSessions(self::TEST_USER, false));
		$this->assertNotContains($session1->getId(), $remainingIds);
		$this->assertNotContains($session2->getId(), $remainingIds);
		$this->assertNotContains($session3->getId(), $remainingIds);
		$this->assertCount(0, $this->messageMapper->getMessages($session1->getId(), 0, 100));
		$this->assertCount(0, $this->messageMapper->getMessages($session2->getId(), 0, 100));
		$this->assertCount(0, $this->messageMapper->getMessages($session3->getId(), 0, 100));
	}

	public function testDeleteSessionsLeavesOtherUsersDataAlone(): void {
		$ownSession = $this->createSession(self::TEST_USER);
		$otherSession = $this->createSession(self::OTHER_USER);
		$this->createMessage($ownSession->getId());
		$otherMessage = $this->createMessage($otherSession->getId());

		// the other user's session ID is passed but must not be deleted,
		// unknown IDs are ignored, duplicates are harmless
		$this->service->deleteSessions(self::TEST_USER, [$ownSession->getId(), $otherSession->getId(), $otherSession->getId(), 999999999]);

		$remainingOwnIds = array_map(static function (Session $session) {
			return $session->getId();
		}, $this->sessionMapper->getUserSessions(self::TEST_USER, false));
		$this->assertNotContains($ownSession->getId(), $remainingOwnIds);
		$this->assertCount(0, $this->messageMapper->getMessages($ownSession->getId(), 0, 100));

		$remainingOtherIds = array_map(static function (Session $session) {
			return $session->getId();
		}, $this->sessionMapper->getUserSessions(self::OTHER_USER, false));
		$this->assertContains($otherSession->getId(), $remainingOtherIds);
		$this->assertContains($otherMessage->getContent(), array_map(static function (Message $message) {
			return $message->getContent();
		}, $this->messageMapper->getMessages($otherSession->getId(), 0, 100)));
	}

	public function testDeleteSessionsWithEmptyList(): void {
		$session = $this->createSession(self::TEST_USER);

		$this->service->deleteSessions(self::TEST_USER, []);

		$remainingIds = array_map(static function (Session $session) {
			return $session->getId();
		}, $this->sessionMapper->getUserSessions(self::TEST_USER, false));
		$this->assertContains($session->getId(), $remainingIds);
	}

	public function testDeleteSessionsWithoutUser(): void {
		$this->expectException(UnauthorizedException::class);
		$this->service->deleteSessions(null, [1, 2]);
	}
}
