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
use OCA\Assistant\Service\SessionSummaryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\ContextAgentInteraction;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Passing the Chat User Instructions to the Context Agent (nextcloud/assistant#338)
 */
class ChatServiceAgencyTest extends TestCase {

	private const USER_ID = 'agency_test_user';
	private const SESSION_ID = 42;
	private const USER_INSTRUCTIONS = 'Always answer like a pirate.';

	private ChatService $service;
	private MessageMapper&MockObject $messageMapper;
	private IManager&MockObject $taskProcessingManager;
	private SessionMapper&MockObject $sessionMapper;
	private SessionSummaryService&MockObject $sessionSummaryService;
	private IUserManager&MockObject $userManager;
	private IAppConfig&MockObject $appConfig;
	private IL10N&MockObject $l10n;
	private IDBConnection&MockObject $db;
	private LoggerInterface&MockObject $logger;
	private ITimeFactory&MockObject $timeFactory;
	private ?Task $scheduledTask = null;

	protected function setUp(): void {
		parent::setUp();

		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->sessionMapper->method('exists')->willReturn(true);
		$session = new Session();
		$session->setUserId(self::USER_ID);
		$this->sessionMapper->method('getUserSession')->willReturn($session);

		$humanMessage = new Message();
		$humanMessage->setSessionId(self::SESSION_ID);
		$humanMessage->setRole(Message::ROLE_HUMAN);
		$humanMessage->setContent('What is your name?');
		$humanMessage->setAttachments('[]');
		$this->messageMapper = $this->createMock(MessageMapper::class);
		$this->messageMapper->method('getLastHumanMessage')->willReturn($humanMessage);

		// only the classic Context Agent is available, no multimodal or audio agency
		$this->taskProcessingManager = $this->createMock(IManager::class);
		$this->taskProcessingManager->method('getAvailableTaskTypeIds')->willReturn([ContextAgentInteraction::ID]);
		$this->taskProcessingManager->method('getUserTasksByApp')->willReturn([]);
		$this->taskProcessingManager->method('scheduleTask')->willReturnCallback(function (Task $task): void {
			$this->scheduledTask = $task;
		});

		$this->sessionSummaryService = $this->createMock(SessionSummaryService::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => ($parameters !== [] ? vsprintf(str_replace('%n', "\n", $text), $parameters) : $text));

		$this->l10n = $l10n;
		$this->userManager = $this->createMock(IUserManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->rebuildService();
	}

	/**
	 * The service must be rebuilt when the mocked SessionMapper changes:
	 * re-stubbing an already configured mock method would not override
	 * the previously configured return value.
	 */
	private function rebuildService(): void {
		$this->service = new ChatService(
			$this->userManager,
			$this->appConfig,
			$this->l10n,
			$this->sessionMapper,
			$this->messageMapper,
			$this->db,
			$this->sessionSummaryService,
			$this->taskProcessingManager,
			$this->logger,
			$this->timeFactory,
		);
	}

	private function setUserSession(Session $session): void {
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->sessionMapper->method('exists')->willReturn(true);
		$this->sessionMapper->method('getUserSession')->willReturn($session);
		$this->rebuildService();
	}

	private function setAgentOptionalInputs(array $optionalInputShape): void {
		$this->taskProcessingManager->method('getAvailableTaskTypes')->willReturn([
			ContextAgentInteraction::ID => ['optionalInputShape' => $optionalInputShape],
		]);
	}

	private function systemMessage(string $content): Message {
		$message = new Message();
		$message->setSessionId(self::SESSION_ID);
		$message->setRole(Message::ROLE_SYSTEM);
		$message->setContent($content);
		return $message;
	}

	public function testUserInstructionsArePassedToTheAgent(): void {
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->method('getFirstNMessages')
			->with(self::SESSION_ID, 1, Message::ROLE_SYSTEM)
			->willReturn($this->systemMessage(self::USER_INSTRUCTIONS));

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(ContextAgentInteraction::ID, $this->scheduledTask->getTaskTypeId());
		$this->assertSame(self::USER_INSTRUCTIONS, $this->scheduledTask->getInput()['user_instructions'] ?? null);
	}

	public function testUserInstructionsAreNotSentWhenTheAgentDoesNotSupportThem(): void {
		// e.g. an older Context Agent version that does not declare the optional input
		$this->setAgentOptionalInputs([]);
		$this->messageMapper->expects($this->never())->method('getFirstNMessages');

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertArrayNotHasKey('user_instructions', $this->scheduledTask->getInput());
	}

	public function testUserInstructionsAreNotSentWhenTheSessionHasNoSystemMessage(): void {
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->method('getFirstNMessages')->willThrowException(new DoesNotExistException('no system message'));

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertArrayNotHasKey('user_instructions', $this->scheduledTask->getInput());
	}

	public function testTheAgentRunsWithoutUserInstructionsWhenTheyCannotBeRead(): void {
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->method('getFirstNMessages')->willThrowException(new Exception('database error'));

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertArrayNotHasKey('user_instructions', $this->scheduledTask->getInput());
	}

	public function testUserInstructionsAreNotSentForAssignmentRuns(): void {
		// assignments run unattended, instructions like "ask for clarification" do not apply
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->expects($this->never())->method('getFirstNMessages');

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(ContextAgentInteraction::ID, $this->scheduledTask->getTaskTypeId());
		$this->assertArrayNotHasKey('user_instructions', $this->scheduledTask->getInput());
	}

	public function testTaskIsScheduledAsTheSessionOwner(): void {
		$this->setAgentOptionalInputs([]);

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(self::USER_ID, $this->scheduledTask->getUserId());
	}

	public function testAgentSessionsScheduleAsTheAgentOwnerWithTheRequesterMemories(): void {
		$agentUserId = 'agent_user_id';
		$session = new Session();
		$session->setUserId($agentUserId);
		$session->setRequesterUserId(self::USER_ID);
		$this->setUserSession($session);
		$this->setAgentOptionalInputs(['memories' => 'shape']);
		$this->messageMapper->method('getFirstNMessages')->willThrowException(new DoesNotExistException('no system message'));
		$this->sessionSummaryService->expects($this->once())
			->method('getMemories')
			->with(self::USER_ID)
			->willReturn([]);

		$this->service->scheduleMessageGeneration($agentUserId, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame($agentUserId, $this->scheduledTask->getUserId());
		$this->assertArrayHasKey('memories', $this->scheduledTask->getInput());
	}

	/**
	 * The generation tasks of agent sessions must describe the human requester
	 * and the agent: without it, the model would not know either identity.
	 */
	public function testAgentSessionsGetTheIdentityLine(): void {
		$session = new Session();
		$session->setUserId('agent_user_id');
		$session->setRequesterUserId(self::USER_ID);
		$this->setUserSession($session);
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->method('getFirstNMessages')
			->with(self::SESSION_ID, 1, Message::ROLE_SYSTEM)
			->willReturn($this->systemMessage(self::USER_INSTRUCTIONS));

		$this->service->scheduleMessageGeneration('agent_user_id', self::SESSION_ID, 0, 'My Group Room');

		$this->assertNotNull($this->scheduledTask);
		$userInstructions = $this->scheduledTask->getInput()['user_instructions'] ?? null;
		$this->assertNotNull($userInstructions);
		// the agent identity and the requester identity are both in the line
		$this->assertStringContainsString('agent_user_id', $userInstructions);
		$this->assertStringContainsString(self::USER_ID, $userInstructions);
		$this->assertStringContainsString('My Group Room', $userInstructions);
		// the session instructions must still be part of the agent context
		$this->assertStringContainsString(self::USER_INSTRUCTIONS, $userInstructions);
	}

	public function testHumanSessionsGetNoIdentityLine(): void {
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->method('getFirstNMessages')
			->with(self::SESSION_ID, 1, Message::ROLE_SYSTEM)
			->willReturn($this->systemMessage(self::USER_INSTRUCTIONS));

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(self::USER_INSTRUCTIONS, $this->scheduledTask->getInput()['user_instructions'] ?? null);
	}
}
