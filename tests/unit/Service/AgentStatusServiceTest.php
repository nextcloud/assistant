<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Service\AgentStatusService;
use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Service\StatusService;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\L10N\IFactory;
use OCP\UserStatus\IUserStatus;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AgentStatusServiceTest extends TestCase {

	private IAppManager&MockObject $appManager;
	private ContainerInterface&MockObject $container;
	private IFactory&MockObject $l10nFactory;
	private LoggerInterface&MockObject $logger;
	private StatusService&MockObject $userStatusService;
	private AgentStatusService $service;

	protected function setUp(): void {
		parent::setUp();

		if (!class_exists('OCA\UserStatus\Service\StatusService')) {
			$this->markTestSkipped('user_status is not installed');
		}

		$this->appManager = $this->createMock(IAppManager::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->userStatusService = $this->createMock(StatusService::class);

		$this->appManager->method('isInstalled')
			->with('user_status')
			->willReturn(true);
		$this->container->method('get')
			->with(StatusService::class)
			->willReturn($this->userStatusService);

		$this->service = $this->getService();
	}

	private function getService(): AgentStatusService {
		return new AgentStatusService(
			$this->appManager,
			$this->container,
			$this->l10nFactory,
			$this->logger,
		);
	}

	private function stubDefaultStatusTranslation(string $translated): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->with(AgentStatusService::DEFAULT_STATUS_MESSAGE)
			->willReturn($translated);
		$this->l10nFactory->method('findGenericLanguage')
			->with('assistant')
			->willReturn('de');
		$this->l10nFactory->method('get')
			->with('assistant', 'de')
			->willReturn($l10n);
	}

	public function testGetDefaultStatusMessageUsesTheInstanceDefaultLanguage(): void {
		$this->stubDefaultStatusTranslation('translated default');
		$this->assertSame('translated default', $this->service->getDefaultStatusMessage());
	}

	public function testGetStatusFallsBackToTheDefaultMessage(): void {
		$this->stubDefaultStatusTranslation('translated default');
		$this->userStatusService->method('findByUserId')
			->with('bob')
			->willThrowException(new DoesNotExistException('no status'));
		$this->assertSame('translated default', $this->service->getStatus('bob'));
	}

	public function testGetStatusReturnsTheCustomMessage(): void {
		// the custom message is a magic Entity getter, it cannot be mocked
		$status = new UserStatus();
		$status->setCustomMessage('Doing something');
		$this->userStatusService->method('findByUserId')
			->with('bob')
			->willReturn($status);
		$this->assertSame('Doing something', $this->service->getStatus('bob'));
	}

	public function testGetStatusWhenUserStatusIsDisabled(): void {
		$this->stubDefaultStatusTranslation('translated default');
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('isInstalled')->willReturn(false);
		$this->container->expects($this->never())->method('get');

		$this->assertSame('translated default', $this->getService()->getStatus('bob'));
	}

	public function testSetAgentStatusSetsOnlinePresenceAndTheMessage(): void {
		$this->userStatusService->expects($this->once())
			->method('setStatus')
			->with('bob', IUserStatus::ONLINE, null, true);
		$this->userStatusService->expects($this->once())
			->method('setCustomMessage')
			->with('bob', null, 'Hello', null);

		$this->service->setAgentStatus('bob', 'Hello');
	}

	public function testSetAgentStatusClearsTheMessage(): void {
		$this->userStatusService->expects($this->once())
			->method('setStatus')
			->with('bob', IUserStatus::ONLINE, null, true);
		$this->userStatusService->expects($this->once())
			->method('clearMessage')
			->with('bob');
		$this->userStatusService->expects($this->never())
			->method('setCustomMessage');

		$this->service->setAgentStatus('bob', '');
	}

	public function testSetAgentStatusIsANoOpWhenUserStatusIsDisabled(): void {
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('isInstalled')->willReturn(false);
		$this->container->expects($this->never())->method('get');

		$this->getService()->setAgentStatus('bob', 'Hello');
	}

	public function testSettingTheStatusFailureIsLoggedAndIgnored(): void {
		$this->userStatusService->method('setStatus')
			->willThrowException(new \Exception('boom'));
		$this->logger->expects($this->once())->method('warning');

		$this->service->setAgentStatus('bob', 'Hello');
	}

	public function testTheUserStatusServiceIsOnlyResolvedOnce(): void {
		$this->container->expects($this->once())
			->method('get')
			->with(StatusService::class)
			->willReturn($this->userStatusService);

		$this->service->setAgentStatus('bob', 'Hello');
		$this->service->setAgentStatus('bob', 'Hello');
	}

	public function testContainerFailureFallsBackToTheDefaultMessage(): void {
		$this->stubDefaultStatusTranslation('translated default');
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->container->method('get')
			->willThrowException(new \Exception('not resolvable'));
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame('translated default', $this->service->getStatus('bob'));
	}
}
