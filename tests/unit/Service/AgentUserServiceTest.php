<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Service\AgentUserException;
use OCA\Assistant\Service\AgentUserService;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AgentUserServiceTest extends TestCase {

	private IUserManager&MockObject $userManager;
	private AgentUserService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->service = new AgentUserService($this->userManager);
	}

	public function testCreatesUserWithRandomPasswordAndDisplayName(): void {
		$this->userManager->method('userExists')->willReturn(false);
		$this->userManager->expects($this->once())
			->method('createUser')
			->with('my_agent', $this->callback(static function (string $password) {
				return strlen($password) >= 32 && ctype_alnum($password);
			}))
			->willReturnCallback(function (string $userId, string $password) {
				$user = $this->createConfiguredMock(IUser::class, ['getUID' => $userId]);
				return $user;
			});

		$user = $this->service->createAgentUser('My Agent', 'my_agent');
		$this->assertSame('my_agent', $user->getUID());
	}

	public function testRejectsInvalidUserIds(): void {
		$this->userManager->expects($this->never())->method('createUser');
		$this->expectException(AgentUserException::class);
		$this->service->createAgentUser('Agent', 'invalid uid!');
	}

	public function testRejectsExistingUserIds(): void {
		$this->userManager->method('userExists')->willReturn(true);
		$this->userManager->expects($this->never())->method('createUser');
		$this->expectException(AgentUserException::class);
		$this->service->createAgentUser('Agent', 'admin');
	}
}
