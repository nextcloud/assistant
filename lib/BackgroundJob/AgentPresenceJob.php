<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\BackgroundJob;

use OCA\Assistant\Service\AgentsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IUserStatus;
use Psr\Log\LoggerInterface;

/**
 * Emulates the heartbeat of the agent users so that their presence
 * stays online: they never log in, so no client would refresh their
 * user status timestamp and user_status would set them offline.
 */
class AgentPresenceJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private AgentsService $agentsService,
		private IUserManager $userManager,
		private IEventDispatcher $eventDispatcher,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// shorter than the 15 minutes after which user_status
		// invalidates an "online" presence
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	#[\Override]
	protected function run(mixed $argument): void {
		try {
			$agents = $this->agentsService->getAllAgents();
		} catch (\Throwable $e) {
			$this->logger->warning('Could not load the agents to keep their presence online', ['exception' => $e]);
			return;
		}
		foreach ($agents as $agent) {
			$user = $this->userManager->get($agent->getUserId());
			if ($user === null) {
				continue;
			}
			try {
				$this->eventDispatcher->dispatchTyped(new UserLiveStatusEvent(
					$user,
					IUserStatus::ONLINE,
					$this->time->getTime(),
				));
			} catch (\Throwable $e) {
				// keep going, the remaining agents still need their heartbeat
				$this->logger->warning('Could not refresh the presence of the agent user "' . $agent->getUserId() . '"', ['exception' => $e]);
			}
		}
	}
}
