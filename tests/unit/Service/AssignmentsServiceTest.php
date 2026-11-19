<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\BadRequestException;
use OCA\Assistant\Service\ChatService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AssignmentsServiceTest extends TestCase {

	private AssignmentMapper&MockObject $assignmentMapper;
	private ChatService&MockObject $chatService;
	private AssignmentsService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->chatService = $this->createMock(ChatService::class);
		$this->service = new AssignmentsService(
			$this->assignmentMapper,
			$this->createMock(SessionMapper::class),
			$this->chatService,
			$this->createMock(ITimeFactory::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IJobList::class),
			$this->createMock(IL10N::class),
			$this->createMock(IDateTimeZone::class),
			$this->createMock(IUserManager::class),
		);
	}

	public static function dataInvalidTitle(): array {
		return [
			['', 'The title must not be empty'],
			[" \t\n", 'The title must not be empty'],
			[str_repeat('ä', 257), 'The title must be at most 256 characters'],
		];
	}

	/**
	 * @dataProvider dataInvalidTitle
	 */
	public function testCreateRejectsInvalidTitle(string $title, string $error): void {
		$this->assignmentMapper->expects(self::never())->method('insert');
		$this->chatService->expects(self::never())->method('createChatSession');

		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage($error);
		$this->service->createAssignment('alice', $title, 'say hi', 1767225600, 'FREQ=DAILY', 'UTC');
	}
}
