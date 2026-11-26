<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\BadRequestException;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\SessionSummaryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\L10N\IFactory;
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
	private IConfig&MockObject $config;
	private IL10N&MockObject $l10n;
	private IDBConnection&MockObject $db;
	private LoggerInterface&MockObject $logger;
	private ITimeFactory&MockObject $timeFactory;
	private ?Task $scheduledTask = null;
	/** @var list<int> */
	private array $cancelledTaskIds = [];
	// the user's UI language
	private string $userLanguage = 'de';

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
		$this->config = $this->createMock(IConfig::class);
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
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('getUserLanguage')->willReturnCallback(fn (): string => $this->userLanguage);

		$this->service = new ChatService(
			$this->userManager,
			$this->appConfig,
			$this->config,
			$this->l10n,
			$this->sessionMapper,
			$this->messageMapper,
			$this->db,
			$this->sessionSummaryService,
			$this->taskProcessingManager,
			$this->logger,
			$this->timeFactory,
			$l10nFactory,
		);
	}

	private function setUserSession(Session $session): void {
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->sessionMapper->method('exists')->willReturn(true);
		$this->sessionMapper->method('getUserSession')->willReturn($session);
		$this->rebuildService();
	}

	/**
	 * The expected language instruction, e.g. "German (de)" with the intl extension or just "de" without it
	 */
	private static function languageInstructions(string $languageCode): string {
		$languageName = class_exists(\Locale::class) ? \Locale::getDisplayLanguage($languageCode, 'en') : '';
		$language = $languageName !== '' && $languageName !== $languageCode
			? $languageName . ' (' . $languageCode . ')'
			: $languageCode;
		return 'If no output language has been requested in the task or in the instructions above, write your output in ' . $language . ', which is the language the user has set for their user interface.';
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

	public function testChatUserInstructionsAreNotSentForAssignmentRuns(): void {
		// assignments run unattended, instructions like "ask for clarification" do not apply
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->expects($this->never())->method('getFirstNMessages');

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(ContextAgentInteraction::ID, $this->scheduledTask->getTaskTypeId());
		// only the default scheduled task instructions and the language instruction
		$this->assertSame(
			[Application::ASSIGNMENT_USER_INSTRUCTIONS, self::languageInstructions('de')],
			explode("\n\n", $this->scheduledTask->getInput()['user_instructions'] ?? ''),
		);
	}

	/**
	 * @param array<string, string> $appValues
	 * @param array<string, string> $userValues
	 */
	private function setInstructionSettings(array $appValues, array $userValues): void {
		$this->appConfig->method('getValueString')
			->willReturnCallback(static fn (string $app, string $key, string $default = '') => $appValues[$key] ?? $default);
		$this->config->method('getUserValue')
			->willReturnCallback(static fn (string $userId, string $app, string $key, $default = '') => $userValues[$key] ?? $default);
	}

	public function testAssignmentInstructionsArePassedToTheAgent(): void {
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->messageMapper->expects($this->never())->method('getFirstNMessages');
		$this->setInstructionSettings([
			'common_user_instructions' => 'Admin common.',
			'chat_user_instructions' => 'Admin chat.',
			'assignment_user_instructions' => 'Admin assignment.',
			'chat_personal_instructions_enabled' => '1',
		], [
			'common_personal_instructions' => 'User common.',
			'chat_personal_instructions' => 'User chat.',
			'assignment_personal_instructions' => 'User assignment.',
		]);

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(
			"Admin common.\n\nAdmin assignment.\n\n" . Application::PERSONAL_INSTRUCTIONS_PREAMBLE . "\n\nUser common.\n\nUser assignment.\n\n" . self::languageInstructions('de'),
			$this->scheduledTask->getInput()['user_instructions'] ?? null,
		);
	}

	public function testPersonalAssignmentInstructionsAreIgnoredWhenDisabledByTheAdmin(): void {
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->setInstructionSettings([
			'assignment_user_instructions' => 'Admin assignment.',
			'chat_personal_instructions_enabled' => '0',
		], [
			'common_personal_instructions' => 'User common.',
			'assignment_personal_instructions' => 'User assignment.',
		]);

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame("Admin assignment.\n\n" . self::languageInstructions('de'), $this->scheduledTask->getInput()['user_instructions'] ?? null);
	}

	public function testAssignmentInstructionsAreNotSentWhenTheAgentDoesNotSupportThem(): void {
		$this->setAgentOptionalInputs([]);
		$this->setInstructionSettings(['assignment_user_instructions' => 'Admin assignment.'], []);

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertArrayNotHasKey('user_instructions', $this->scheduledTask->getInput());
	}

	/**
	 * An assignment run ordered through an agent in Talk is still performed
	 * by the agent: the task carries the identity line so the model knows
	 * who it is and who ordered the task, and the memories come from the
	 * human requester, but the session's own user instructions are not sent,
	 * they are meant for interactive chats.
	 */
	public function testAssignmentRunsCarryTheAgentIdentityAndTheRequesterMemories(): void {
		$agentUserId = 'agent_user_id';
		$session = new Session();
		$session->setUserId($agentUserId);
		$session->setRequesterUserId(self::USER_ID);
		$this->setUserSession($session);
		$this->setAgentOptionalInputs(['user_instructions' => 'shape', 'memories' => 'shape']);
		// the session's own instructions are not read for unattended runs
		$this->messageMapper->expects($this->never())->method('getFirstNMessages');
		$this->sessionSummaryService->expects($this->once())
			->method('getMemories')
			->with(self::USER_ID)
			->willReturn(['remember me']);

		$this->service->scheduleAssignmentMessageGeneration($agentUserId, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$input = $this->scheduledTask->getInput();
		$this->assertSame(ContextAgentInteraction::ID, $this->scheduledTask->getTaskTypeId());
		$this->assertStringContainsString('You, the assistant', $input['user_instructions'] ?? '');
		$this->assertSame(['remember me'], $input['memories'] ?? null);
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

	private function humanMessage(string $content, int $timestamp): Message {
		$message = new Message();
		$message->setSessionId(self::SESSION_ID);
		$message->setRole(Message::ROLE_HUMAN);
		$message->setContent($content);
		$message->setTimestamp($timestamp);
		$message->setAttachments('[]');
		return $message;
	}

	private function assistantMessage(string $content, int $timestamp): Message {
		$message = new Message();
		$message->setSessionId(self::SESSION_ID);
		$message->setRole(Message::ROLE_ASSISTANT);
		$message->setContent($content);
		$message->setTimestamp($timestamp);
		$message->setAttachments('[]');
		return $message;
	}

	private function pendingGenerationTask(int $id): Task {
		$task = new Task(ContextAgentInteraction::ID, [], 'assistant:chatty-llm', self::USER_ID, 'chatty-llm:' . self::SESSION_ID);
		$task->setId($id);
		$task->setStatus(Task::STATUS_SCHEDULED);
		return $task;
	}

	/**
	 * A fresh IManager mock tracking the cancelled task ids and the pending
	 * tasks: cancelTask marks the task cancelled and removes it from the ones
	 * returned by getUserTasksByApp, getTask still returns it (as cancelled),
	 * like the task processing manager does.
	 */
	private function setPendingGenerationTasks(array $tasks): void {
		$pending = $tasks;
		$allTasks = [];
		foreach ($tasks as $task) {
			$allTasks[$task->getId()] = $task;
		}
		$taskProcessingManager = $this->createMock(IManager::class);
		$taskProcessingManager->method('getAvailableTaskTypeIds')->willReturn([ContextAgentInteraction::ID]);
		$taskProcessingManager->method('getAvailableTaskTypes')->willReturn([
			ContextAgentInteraction::ID => ['optionalInputShape' => []],
		]);
		$taskProcessingManager->method('getUserTasksByApp')->willReturnCallback(static function () use (&$pending): array {
			return $pending;
		});
		$taskProcessingManager->method('cancelTask')->willReturnCallback(function (int $taskId) use (&$pending, &$allTasks): void {
			$this->cancelledTaskIds[] = $taskId;
			$allTasks[$taskId]?->setStatus(Task::STATUS_CANCELLED);
			$pending = array_values(array_filter($pending, static fn (Task $task) => $task->getId() !== $taskId));
		});
		$taskProcessingManager->method('getTask')->willReturnCallback(static function (int $taskId) use (&$allTasks): Task {
			return $allTasks[$taskId];
		});
		$taskProcessingManager->method('scheduleTask')->willReturnCallback(function (Task $task): void {
			$this->scheduledTask = $task;
		});
		$this->taskProcessingManager = $taskProcessingManager;
		$this->rebuildService();
	}

	/**
	 * A message generation task must not be scheduled while another one is
	 * scheduled or running for the session, unless cancellation is requested.
	 */
	public function testSchedulingFailsWhileTheSessionIsThinking(): void {
		$this->setPendingGenerationTasks([$this->pendingGenerationTask(7)]);

		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('session_already_thinking');
		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);
	}

	/**
	 * In the Talk integration a new human message supersedes the one being
	 * answered: the pending generation is cancelled and a new one is scheduled
	 * for the latest message.
	 */
	public function testSchedulingCancelsThePendingGenerationWhenRequested(): void {
		$this->setPendingGenerationTasks([$this->pendingGenerationTask(7)]);

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID, 0, null, cancelRunningTask: true);

		$this->assertSame([7], $this->cancelledTaskIds);
		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(ContextAgentInteraction::ID, $this->scheduledTask->getTaskTypeId());
	}

	public function testNoGenerationIsCancelledWhenTheSessionIsNotThinking(): void {
		$this->setPendingGenerationTasks([]);

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID, 0, null, cancelRunningTask: true);

		$this->assertSame([], $this->cancelledTaskIds);
		$this->assertNotNull($this->scheduledTask);
	}

	/**
	 * The Talk stop feature needs to know whether a generation was actually
	 * cancelled to decide between the ⏹️ (ignored) and 🛑 (stopped) outcomes.
	 */
	public function testCancelPendingGenerationTasksReturnsTheCancelledCount(): void {
		$this->setPendingGenerationTasks([$this->pendingGenerationTask(7), $this->pendingGenerationTask(8)]);

		$this->assertSame(2, $this->service->cancelPendingGenerationTasks(self::USER_ID, self::SESSION_ID));
		$this->assertSame([7, 8], $this->cancelledTaskIds);
	}

	/**
	 * Human messages that are pending without an answer in between belong to
	 * the same agency turn: the agent gets all of them as its input.
	 */
	public function testPendingHumanMessagesAreMergedIntoTheAgencyInput(): void {
		$this->setAgentOptionalInputs([]);
		$first = $this->humanMessage('What is your name?', 100);
		$second = $this->humanMessage('And how old are you?', 200);
		$this->messageMapper = $this->createMock(MessageMapper::class);
		$this->messageMapper->method('getLastHumanMessage')->willReturn($second);
		$this->messageMapper->method('getMessages')->willReturn([$first, $second]);
		$this->rebuildService();

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame("What is your name?\n\nAnd how old are you?", $this->scheduledTask->getInput()['input'] ?? null);
	}

	public function testOnlyTrailingHumanMessagesAreMergedIntoTheAgencyInput(): void {
		$this->setAgentOptionalInputs([]);
		$first = $this->humanMessage('What is your name?', 100);
		$answer = $this->assistantMessage('I am the assistant.', 150);
		$second = $this->humanMessage('And how old are you?', 200);
		$this->messageMapper = $this->createMock(MessageMapper::class);
		$this->messageMapper->method('getLastHumanMessage')->willReturn($second);
		$this->messageMapper->method('getMessages')->willReturn([$first, $answer, $second]);
		$this->rebuildService();

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame('And how old are you?', $this->scheduledTask->getInput()['input'] ?? null);
	}

	public function testTheAgencyInputIsTheLastHumanMessageWithoutPendingOnes(): void {
		$this->setAgentOptionalInputs([]);
		$this->messageMapper->method('getMessages')->willReturn([$this->humanMessage('What is your name?', 100)]);

		$this->service->scheduleMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame('What is your name?', $this->scheduledTask->getInput()['input'] ?? null);
	}

	public function testLanguageInstructionsDoNotOverrideALanguageRequestedByTheUser(): void {
		// the UI language is only a fallback, so it comes after the user's instructions
		// instead of being one of the admin instructions that take precedence over them
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->setInstructionSettings([
			'assignment_user_instructions' => 'Admin assignment.',
			'chat_personal_instructions_enabled' => '1',
		], [
			'assignment_personal_instructions' => 'Always answer in French.',
		]);

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(
			"Admin assignment.\n\n" . Application::PERSONAL_INSTRUCTIONS_PREAMBLE . "\n\nAlways answer in French.\n\n" . self::languageInstructions('de'),
			$this->scheduledTask->getInput()['user_instructions'] ?? null,
		);
	}

	public function testLanguageInstructionsFallBackToTheLanguageCode(): void {
		// a code that intl does not know, so there is no language name to show
		$this->userLanguage = 'xx';
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->setInstructionSettings(['assignment_user_instructions' => 'Admin assignment.'], []);

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(
			"Admin assignment.\n\n" . 'If no output language has been requested in the task or in the instructions above, write your output in xx, which is the language the user has set for their user interface.',
			$this->scheduledTask->getInput()['user_instructions'] ?? null,
		);
	}
}
