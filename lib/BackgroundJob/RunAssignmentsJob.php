<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\BackgroundJob;

use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\InternalException;
use OCA\Assistant\Service\UnauthorizedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class RunAssignmentsJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private AssignmentsService $assignmentService,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setAllowParallelRuns(true);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
		$this->setInterval(60 * 10); // 10min
	}
	public function run($argument) {
		$userId = $argument['userId'];
		try {
			$this->assignmentService->runDueAssignmentsForUser($userId);
		} catch (InternalException|UnauthorizedException $e) {
			$this->logger->error('Error running assignments for user ' . $userId, ['exception' => $e]);
		}
	}
}
