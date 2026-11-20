<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Service\AgencyActionService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AgencyActionServiceTest extends TestCase {

	private AgencyActionService $service;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => ($parameters !== [] ? vsprintf(str_replace('%n', "\n", $text), $parameters) : $text));

		$this->service = new AgencyActionService($this->l10n);
	}

	public function testSingleActionWithKnownToolName(): void {
		$pendingActions = json_encode([
			['name' => 'send_email', 'args' => ['subject' => 'Meeting notes', 'to' => 'alice@example.org']],
		]);

		$message = $this->service->formatConfirmationMessage($pendingActions);

		$expected = "I want to perform a sensitive action:\n\n"
			. "**Send an email**\n"
			. "* subject: Meeting notes\n"
			. '* to: alice@example.org';
		$this->assertSame($expected, $message);
	}

	public function testMultipleActionsAreNumberedAndGrouped(): void {
		$pendingActions = json_encode([
			['name' => 'send_email', 'args' => ['subject' => 'Meeting notes']],
			['name' => 'delete_file', 'args' => ['path' => '/Documents/old.txt']],
		]);

		$message = $this->service->formatConfirmationMessage($pendingActions);

		$expected = "I want to perform the following sensitive actions:\n\n"
			. "1. **Send an email**\n"
			. "* subject: Meeting notes\n\n"
			. "2. **Delete file**\n"
			. '* path: /Documents/old.txt';
		$this->assertSame($expected, $message);
	}

	public function testUnknownToolNameIsPrettified(): void {
		$pendingActions = json_encode([
			['name' => 'feed_the_cat', 'args' => ['cat_name' => 'Felix']],
		]);

		$message = $this->service->formatConfirmationMessage($pendingActions);

		$expected = "I want to perform a sensitive action:\n\n"
			. "**Feed the cat**\n"
			. '* cat name: Felix';
		$this->assertSame($expected, $message);
	}

	public function testUnderscoresAreRemovedFromParameterNames(): void {
		$pendingActions = json_encode([
			['name' => 'schedule_event', 'args' => ['event_title' => 'Standup', 'start_date' => '2026-11-13']],
		]);

		$message = $this->service->formatConfirmationMessage($pendingActions);

		$this->assertStringContainsString('* event title: Standup', $message);
		$this->assertStringContainsString('* start date: 2026-11-13', $message);
	}

	public function testListParameterValuesAreJoined(): void {
		$pendingActions = json_encode([
			['name' => 'send_message_to_conversation', 'args' => ['recipients' => ['alice', 'bob']]],
		]);

		$message = $this->service->formatConfirmationMessage($pendingActions);

		$this->assertStringContainsString('* recipients: alice, bob', $message);
	}

	public function testNewlinesInParameterValuesAreStripped(): void {
		$pendingActions = json_encode([
			['name' => 'send_email', 'args' => ['body' => "line one\nline two"]],
		]);

		$message = $this->service->formatConfirmationMessage($pendingActions);

		$this->assertStringContainsString('* body: line one line two', $message);
	}

	public function testInvalidJsonReturnsNull(): void {
		$this->assertNull($this->service->formatConfirmationMessage('not json'));
	}

	public function testEmptyActionListReturnsNull(): void {
		$this->assertNull($this->service->formatConfirmationMessage('[]'));
	}

	public function testActionWithoutNameNorArgsIsSkipped(): void {
		$pendingActions = json_encode([['destructive' => true]]);

		$this->assertNull($this->service->formatConfirmationMessage($pendingActions));
	}

	public function testActionDataProvidesTitlesAndIcons(): void {
		$this->assertSame('Send an email', $this->service->getActionTitle('send_email'));
		$this->assertSame('EmailPlus', $this->service->getActionIcon('send_email'));
		$this->assertNull($this->service->getActionTitle('unknown_tool'));
		$this->assertNull($this->service->getActionIcon('unknown_tool'));
	}
}
