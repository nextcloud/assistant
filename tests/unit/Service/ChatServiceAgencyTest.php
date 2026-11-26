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
	private IAppConfig&MockObject $appConfig;
	private IConfig&MockObject $config;
	private ?Task $scheduledTask = null;
	// the user's UI language
	private string $userLanguage = 'de';

	protected function setUp(): void {
		parent::setUp();

		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->method('exists')->willReturn(true);
		$session = new Session();
		$session->setUserId(self::USER_ID);
		$sessionMapper->method('getUserSession')->willReturn($session);

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

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->config = $this->createMock(IConfig::class);

		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('getUserLanguage')->willReturnCallback(fn (): string => $this->userLanguage);

		$this->service = new ChatService(
			$this->createMock(IUserManager::class),
			$this->appConfig,
			$this->config,
			$this->createMock(IL10N::class),
			$sessionMapper,
			$this->messageMapper,
			$this->createMock(IDBConnection::class),
			$this->createMock(SessionSummaryService::class),
			$this->taskProcessingManager,
			$this->createMock(LoggerInterface::class),
			$this->createMock(ITimeFactory::class),
			$l10nFactory,
		);
	}

	/**
	 * The expected language instruction, e.g. "German (de)" with the intl extension or just "de" without it
	 */
	private static function languageInstructions(string $languageCode): string {
		$languageName = class_exists(\Locale::class) ? \Locale::getDisplayLanguage($languageCode, 'en') : '';
		$language = $languageName !== '' && $languageName !== $languageCode
			? $languageName . ' (' . $languageCode . ')'
			: $languageCode;
		return 'Write your output in ' . $language . ', which is the language the user has set for their user interface, unless the task explicitly asks for a different language.';
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
		// only the language instruction and the default scheduled task instructions
		$this->assertSame(
			[self::languageInstructions('de'), Application::ASSIGNMENT_USER_INSTRUCTIONS],
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
			self::languageInstructions('de') . "\n\nAdmin common.\n\nAdmin assignment.\n\n" . Application::PERSONAL_INSTRUCTIONS_PREAMBLE . "\n\nUser common.\n\nUser assignment.",
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
		$this->assertSame(self::languageInstructions('de') . "\n\nAdmin assignment.", $this->scheduledTask->getInput()['user_instructions'] ?? null);
	}

	public function testAssignmentInstructionsAreNotSentWhenTheAgentDoesNotSupportThem(): void {
		$this->setAgentOptionalInputs([]);
		$this->setInstructionSettings(['assignment_user_instructions' => 'Admin assignment.'], []);

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertArrayNotHasKey('user_instructions', $this->scheduledTask->getInput());
	}

	public function testLanguageInstructionsFallBackToTheLanguageCode(): void {
		// a code that intl does not know, so there is no language name to show
		$this->userLanguage = 'xx';
		$this->setAgentOptionalInputs(['user_instructions' => 'shape']);
		$this->setInstructionSettings(['assignment_user_instructions' => 'Admin assignment.'], []);

		$this->service->scheduleAssignmentMessageGeneration(self::USER_ID, self::SESSION_ID);

		$this->assertNotNull($this->scheduledTask);
		$this->assertSame(
			'Write your output in xx, which is the language the user has set for their user interface, unless the task explicitly asks for a different language.' . "\n\nAdmin assignment.",
			$this->scheduledTask->getInput()['user_instructions'] ?? null,
		);
	}
}
