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
use OCA\Assistant\Service\NotFoundException;
use OCA\Assistant\Service\UnauthorizedException;
use OCP\IUserManager;
use OCP\Server;
use Test\TestCase;

/**
 * Integration tests for the ChatService chat session deletion methods
 * and for the methods that take the ID of a session of another user.
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
			$this->service->deleteSessions($uid, $sessionIds);
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

	public function testDeleteSession(): void {
		$session = $this->createSession(self::TEST_USER);
		$this->createMessage($session->getId());

		$this->service->deleteSession(self::TEST_USER, $session->getId());

		$remainingIds = array_map(static function (Session $session) {
			return $session->getId();
		}, $this->sessionMapper->getUserSessions(self::TEST_USER, false));
		$this->assertNotContains($session->getId(), $remainingIds);
		$this->assertCount(0, $this->messageMapper->getMessages($session->getId(), 0, 100));
	}

	public function testDeleteSessionLeavesOtherUsersDataAlone(): void {
		$otherSession = $this->createSession(self::OTHER_USER);
		$this->createMessage($otherSession->getId());

		$this->service->deleteSession(self::TEST_USER, $otherSession->getId());

		$this->assertSame(self::OTHER_USER, $this->sessionMapper->getUserSession(self::OTHER_USER, $otherSession->getId())->getUserId());
		$this->assertCount(1, $this->messageMapper->getMessages($otherSession->getId(), 0, 100));
	}

	/**
	 * Every public method that takes a session ID must leave a session of another user alone.
	 * The list of calls is compared with the class, so a new method has to be added here.
	 */
	public function testSessionIdMethodsLeaveOtherUsersSessionsAlone(): void {
		$otherSession = $this->createSession(self::OTHER_USER);
		$otherMessage = $this->createMessage($otherSession->getId());
		$sessionId = $otherSession->getId();
		$messageId = $otherMessage->getId();

		$calls = [
			'cancelPendingGenerationTasks' => fn () => $this->service->cancelPendingGenerationTasks(self::TEST_USER, $sessionId),
			'createMessage' => fn () => $this->service->createMessage(self::TEST_USER, $sessionId, Message::ROLE_ASSISTANT, 'planted'),
			'deleteSession' => fn () => $this->service->deleteSession(self::TEST_USER, $sessionId),
			'deleteSessionMessage' => fn () => $this->service->deleteSessionMessage(self::TEST_USER, $sessionId, $messageId),
			'getSessionMessage' => fn () => $this->service->getSessionMessage(self::TEST_USER, $sessionId, $messageId),
			'getSessionMessages' => fn () => $this->service->getSessionMessages(self::TEST_USER, $sessionId),
			'scheduleAssignmentMessageGeneration' => fn () => $this->service->scheduleAssignmentMessageGeneration(self::TEST_USER, $sessionId),
			'scheduleMessageGeneration' => fn () => $this->service->scheduleMessageGeneration(self::TEST_USER, $sessionId),
			'scheduleTitleGeneration' => fn () => $this->service->scheduleTitleGeneration(self::TEST_USER, $sessionId),
			'updateSession' => fn () => $this->service->updateSession(self::TEST_USER, $sessionId, 'renamed', true),
		];

		$methodsWithSessionId = [];
		foreach ((new \ReflectionClass(ChatService::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			foreach ($method->getParameters() as $parameter) {
				if ($parameter->getName() === 'sessionId') {
					$methodsWithSessionId[] = $method->getName();
				}
			}
		}
		$checkedMethods = array_keys($calls);
		sort($methodsWithSessionId);
		sort($checkedMethods);
		$this->assertSame($methodsWithSessionId, $checkedMethods, 'A ChatService method takes a session ID but is not checked here');

		foreach ($calls as $name => $call) {
			try {
				$result = $call();
				// cancelPendingGenerationTasks returns the number of cancelled tasks,
				// a non-zero count would mean it cancelled another user's tasks
				$this->assertTrue($result === null || (is_int($result) && $result === 0), $name . ' returned data of another user');
			} catch (NotFoundException) {
				// refused
			}
			$session = $this->sessionMapper->getUserSession(self::OTHER_USER, $sessionId);
			$this->assertNull($session->getTitle(), $name . ' changed the title');
			$this->assertFalse((bool)$session->getIsRemembered(), $name . ' changed the session');
			$messages = $this->messageMapper->getMessages($sessionId, 0, 100);
			$this->assertCount(1, $messages, $name . ' changed the messages');
			$this->assertSame($otherMessage->getContent(), $messages[0]->getContent(), $name . ' changed a message');
		}
	}
}
