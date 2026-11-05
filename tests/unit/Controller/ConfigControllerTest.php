<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Controller\ConfigController;
use OCA\Assistant\Service\AgentSkillsService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConfigControllerTest extends TestCase {

	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private AgentSkillsService&MockObject $agentSkillsService;
	private IEventDispatcher&MockObject $eventDispatcher;
	/** @var list<CriticalActionPerformedEvent> */
	private array $auditEvents = [];
	private ConfigController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->agentSkillsService = $this->createMock(AgentSkillsService::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->auditEvents = [];
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (object $event): void {
				if ($event instanceof CriticalActionPerformedEvent) {
					$this->auditEvents[] = $event;
				}
			});

		$this->controller = new ConfigController(
			'assistant',
			$this->createMock(IRequest::class),
			$this->config,
			$this->appConfig,
			$this->agentSkillsService,
			'admin',
			$this->eventDispatcher,
		);
	}

	/**
	 * @param array<string, string> $stored key/value pairs currently stored in the app config
	 */
	private function mockAppConfig(array $stored): void {
		$this->appConfig->method('hasKey')
			->willReturnCallback(fn (string $app, string $key, ?bool $lazy = false): bool => array_key_exists($key, $stored));
		$this->appConfig->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default = '', ?bool $lazy = false): string => $stored[$key] ?? $default);
	}

	public function testSetAdminConfigStoresOnlyAdminEnabledNonLazy(): void {
		$this->mockAppConfig(['assistant_enabled' => '1']);
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'assistant_enabled', '0', false);
		$this->appConfig->expects($this->once())
			->method('hasKey')
			->with(Application::APP_ID, 'assistant_enabled', false);

		$this->controller->setAdminConfig(['assistant_enabled' => '0']);
	}

	public function testSetAdminConfigAuditLogsChangedValues(): void {
		$this->mockAppConfig([
			'assistant_enabled' => '1',
			'chat_last_n_messages' => '10',
		]);
		$this->appConfig->expects($this->exactly(2))
			->method('setValueString')
			->willReturnCallback(function (string $app, string $key, string $value, bool $lazy): bool {
				self::assertSame(Application::APP_ID, $app);
				self::assertSame($key === 'assistant_enabled', !$lazy, 'assistant_enabled must not be stored lazy');
				self::assertSame($key === 'assistant_enabled' ? '0' : '5', $value, 'New value must be stored');
				return true;
			});

		$this->controller->setAdminConfig([
			'assistant_enabled' => '0',
			'chat_last_n_messages' => '5',
		]);

		$this->assertCount(2, $this->auditEvents, 'Should audit both changed settings');
		[$enabledEvent, $messagesEvent] = $this->auditEvents;
		self::assertSame(
			['setting' => 'assistant_enabled', 'oldValue' => '1', 'newValue' => '0', 'user' => 'admin'],
			$enabledEvent->getParameters(),
		);
		self::assertSame(
			['setting' => 'chat_last_n_messages', 'oldValue' => '10', 'newValue' => '5', 'user' => 'admin'],
			$messagesEvent->getParameters(),
		);
	}

	public function testSetAdminConfigAuditLogsPreviouslyUnsetKey(): void {
		$this->mockAppConfig([]);
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'chat_last_n_messages', '5', true);

		$this->controller->setAdminConfig(['chat_last_n_messages' => '5']);

		$this->assertCount(1, $this->auditEvents, 'Should audit the new setting');
		self::assertSame(
			['setting' => 'chat_last_n_messages', 'oldValue' => null, 'newValue' => '5', 'user' => 'admin'],
			$this->auditEvents[0]->getParameters(),
		);
	}

	public function testSetAdminConfigDoesNotAuditUnchangedValues(): void {
		$this->mockAppConfig(['assistant_enabled' => '1']);
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'assistant_enabled', '1', false);
		$this->eventDispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->controller->setAdminConfig(['assistant_enabled' => '1']);

		self::assertCount(0, $this->auditEvents);
	}

	public function testSetGlobalSkillsFolderAuditLogsChange(): void {
		$this->agentSkillsService->method('getGlobalSkillsConfig')
			->willReturn(['admin_uid' => 'admin', 'path' => 'old/skills']);
		$this->agentSkillsService->expects($this->once())
			->method('setGlobalSkillsFolder')
			->with('admin', 'new/skills');

		$this->controller->setGlobalSkillsFolder('new/skills');

		$this->assertCount(1, $this->auditEvents, 'Should audit the change');
		self::assertSame(
			[
				'user' => 'admin',
				'oldPath' => 'old/skills',
				'oldAdminUid' => 'admin',
				'newPath' => 'new/skills',
				'newAdminUid' => 'admin',
			],
			$this->auditEvents[0]->getParameters(),
		);
	}

	public function testSetGlobalSkillsFolderAuditLogsClearing(): void {
		$this->agentSkillsService->method('getGlobalSkillsConfig')
			->willReturn(['admin_uid' => 'admin', 'path' => 'old/skills']);
		$this->agentSkillsService->expects($this->once())
			->method('setGlobalSkillsFolder')
			->with('', '');

		$this->controller->setGlobalSkillsFolder('');

		$this->assertCount(1, $this->auditEvents, 'Should audit clearing the setting');
		self::assertSame(
			[
				'user' => 'admin',
				'oldPath' => 'old/skills',
				'oldAdminUid' => 'admin',
				'newPath' => '',
				'newAdminUid' => '',
			],
			$this->auditEvents[0]->getParameters(),
		);
	}

	public function testSetGlobalSkillsFolderDoesNotAuditUnchangedValue(): void {
		$this->agentSkillsService->method('getGlobalSkillsConfig')
			->willReturn(['admin_uid' => 'admin', 'path' => 'skills']);
		$this->agentSkillsService->expects($this->never())
			->method('setGlobalSkillsFolder');
		$this->eventDispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->controller->setGlobalSkillsFolder('skills');

		self::assertCount(0, $this->auditEvents);
	}
}
