<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\AppInfo\Application;
use OCA\UserStatus\Service\StatusService as UserStatusService;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\L10N\IFactory;
use OCP\UserStatus\IUserStatus;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Read/write access to the user status of the dedicated Nextcloud user backing an agent.
 * The status itself is stored by the user_status app, this is only a wrapper around it.
 */
class AgentStatusService {

	public const DEFAULT_STATUS_MESSAGE = 'I only answer to @mentions';
	/** Same limit as OCA\UserStatus\Service\StatusService::MAXIMUM_MESSAGE_LENGTH */
	public const MAX_STATUS_MESSAGE_LENGTH = 80;

	private ?UserStatusService $userStatusService = null;
	private bool $userStatusServiceResolved = false;

	public function __construct(
		private IAppManager $appManager,
		private ContainerInterface $container,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * The default agent status message, translated in the instance default language
	 */
	public function getDefaultStatusMessage(): string {
		$language = $this->l10nFactory->findGenericLanguage(Application::APP_ID);
		return $this->l10nFactory->get(Application::APP_ID, $language)->t(self::DEFAULT_STATUS_MESSAGE);
	}

	/**
	 * The status message shown for the agent user:
	 * its custom message if any, the default one otherwise
	 */
	public function getStatus(string $agentUserId): string {
		$message = $this->getCustomMessage($agentUserId);
		return ($message !== null && $message !== '') ? $message : $this->getDefaultStatusMessage();
	}

	/**
	 * The custom status message of the agent user,
	 * null when user_status is unavailable or no message is set
	 */
	public function getCustomMessage(string $agentUserId): ?string {
		$userStatusService = $this->getUserStatusService();
		if ($userStatusService === null) {
			return null;
		}
		try {
			return $userStatusService->findByUserId($agentUserId)->getCustomMessage();
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return null;
		} catch (Throwable $e) {
			$this->logger->warning('Failed to read the status of the agent user "' . $agentUserId . '"', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Set the user status of the agent user: online presence and the given custom message.
	 * An empty message clears the status message.
	 */
	public function setAgentStatus(string $agentUserId, string $message): void {
		$userStatusService = $this->getUserStatusService();
		if ($userStatusService === null) {
			return;
		}
		try {
			$userStatusService->setStatus($agentUserId, IUserStatus::ONLINE, null, true);
			if ($message === '') {
				$userStatusService->clearMessage($agentUserId);
			} else {
				$userStatusService->setCustomMessage($agentUserId, null, $message, null);
			}
		} catch (Throwable $e) {
			$this->logger->warning('Failed to set the status of the agent user "' . $agentUserId . '"', ['exception' => $e]);
		}
	}

	/**
	 * The user status service is resolved lazily because user_status is not
	 * part of the server core and can be disabled
	 */
	private function getUserStatusService(): ?UserStatusService {
		if (!$this->userStatusServiceResolved) {
			$this->userStatusServiceResolved = true;
			if (!$this->appManager->isInstalled('user_status')) {
				return null;
			}
			try {
				$this->userStatusService = $this->container->get(UserStatusService::class);
			} catch (Throwable $e) {
				$this->logger->warning('Could not load the user status service', ['exception' => $e]);
			}
		}
		return $this->userStatusService;
	}
}
