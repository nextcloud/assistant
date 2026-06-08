<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests;

use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\SessionSummaryService;
use OCA\Assistant\Service\UnauthorizedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\TaskProcessing\IManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ChatServiceSearchTest extends TestCase {

	private ChatService $service;
	private MessageMapper $messageMapper;

	protected function setUp(): void {
		parent::setUp();

		// Mock all ChatService dependencies
		$this->messageMapper = $this->createMock(MessageMapper::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->service = new ChatService(
			$this->createMock(IUserManager::class),
			$this->createMock(IAppConfig::class),
			$l10n,
			$this->createMock(SessionMapper::class),
			$this->messageMapper,
			$this->createMock(SessionSummaryService::class),
			$this->createMock(IManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ITimeFactory::class),
		);
	}

	public function testSearchMessagesUserIdNull(): void {
		// UserId = null should throw an error
		$this->expectException(UnauthorizedException::class);
		$this->service->searchMessages(null, 'hello');
	}

	public function testSearchMessagesBlankQuery(): void {
		// A blank query should not hit the database but return empty
		$this->messageMapper->expects($this->never())
			->method('searchMessages');

		$result = $this->service->searchMessages('user1', '   ');

		$this->assertSame([], $result['messages']);
		$this->assertSame([], $result['sessionIds']);
	}

	public function testSearchMessagesSameSession(): void {
		// Two messages from the same session
		$msg1 = new Message();
		$msg1->setSessionId(1);
		$msg1->setRole(Message::ROLE_HUMAN);
		$msg1->setContent('hello assistant');
		$msg1->setTimestamp(1000);
		$msg1->setSources('[]');
		$msg1->setAttachments('[]');

		$msg2 = new Message();
		$msg2->setSessionId(1);
		$msg2->setRole(Message::ROLE_ASSISTANT);
		$msg2->setContent('hello human');
		$msg2->setTimestamp(1001);
		$msg2->setSources('[]');
		$msg2->setAttachments('[]');

		$this->messageMapper->expects($this->once())
			->method('searchMessages')
			->with('user1', 'hello')
			->willReturn([$msg1, $msg2]);

		$result = $this->service->searchMessages('user1', 'hello');

		// Two messages returned
		$this->assertCount(2, $result['messages']);
		// Check that the messages have the same session ID
		$this->assertSame([1], $result['sessionIds']);
	}

	public function testSearchMessagesDifferentSession(): void {
		$msg1 = new Message();
		$msg1->setSessionId(2);
		$msg1->setRole(Message::ROLE_HUMAN);
		$msg1->setContent('hello from session 2');
		$msg1->setTimestamp(1000);
		$msg1->setSources('[]');
		$msg1->setAttachments('[]');

		$msg2 = new Message();
		$msg2->setSessionId(3);
		$msg2->setRole(Message::ROLE_HUMAN);
		$msg2->setContent('hello from session 3');
		$msg2->setTimestamp(1001);
		$msg2->setSources('[]');
		$msg2->setAttachments('[]');

		$this->messageMapper->expects($this->once())
			->method('searchMessages')
			->with('user1', 'hello')
			->willReturn([$msg1, $msg2]);

		$result = $this->service->searchMessages('user1', 'hello');

		$this->assertCount(2, $result['messages']);

		// Check that the messages have different session IDs
		$this->assertSame([2, 3], $result['sessionIds']);
	}
}
