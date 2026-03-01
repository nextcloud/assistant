<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Controller;

use OCA\Assistant\Controller\ChattyLLMController;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Service\SessionSummaryService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ChattyLLMControllerSearchTest extends TestCase {

	public function testSearchChatWhenNotLoggedInReturnsUnauthorized(): void {
		$controller = $this->createController(userId: null);
		$response = $controller->searchChat('foo');
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
	}

	public function testSearchChatWithEmptyQueryReturnsEmptySessions(): void {
		$controller = $this->createController(userId: 'user1');
		$response = $controller->searchChat('');
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['sessions' => []], $response->getData());
	}

	public function testSearchChatWithShortQueryReturnsEmptySessions(): void {
		$controller = $this->createController(userId: 'user1');
		$response = $controller->searchChat('a');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['sessions' => []], $response->getData());
	}

	public function testSearchChatWithValidQueryReturnsSessionsFromMapper(): void {
		$session = new Session();
		$session->setId(1);
		$session->setUserId('user1');
		$session->setTitle('Test');
		$session->setTimestamp(time());

		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->expects($this->once())
			->method('searchSessionsByMessageContent')
			->with('user1', 'hello')
			->willReturn([$session]);

		$controller = $this->createController(userId: 'user1', sessionMapper: $sessionMapper);
		$response = $controller->searchChat('hello');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('sessions', $data);
		$this->assertCount(1, $data['sessions']);
		$this->assertSame(1, $data['sessions'][0]['id']);
	}

	public function testSearchChatWhenMapperThrowsReturnsInternalServerError(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->method('searchSessionsByMessageContent')
			->willThrowException(new \OCP\DB\Exception('DB error'));

		$controller = $this->createController(userId: 'user1', sessionMapper: $sessionMapper);
		$response = $controller->searchChat('hello');
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
	}

	private function createController(
		?string $userId = 'user1',
		?SessionMapper $sessionMapper = null,
	): ChattyLLMController {
		$sessionMapper = $sessionMapper ?? $this->createMock(SessionMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new ChattyLLMController(
			'assistant',
			$this->createMock(IRequest::class),
			$sessionMapper,
			$this->createMock(MessageMapper::class),
			$l10n,
			$this->createMock(LoggerInterface::class),
			$this->createMock(ITaskProcessingManager::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(IUserManager::class),
			$userId,
			$this->createMock(SessionSummaryService::class),
		);
	}
}
