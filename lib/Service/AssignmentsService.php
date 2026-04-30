<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\Db\ChattyLLM\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class AssignmentsService {
	public function __construct(
		private AssignmentMapper $assignmentMapper,
		private SessionMapper $sessionMapper,
		private MessageMapper $messageMapper,
		private TaskProcessingService $taskProcessingService,
		private LoggerInterface $logger,
		private IAppConfig $appConfig,
		private IJobList $jobList,
	) {
	}

	public function runDueAssignmentsForUser(string $userId) {
		try {
			foreach ($this->assignmentMapper->findDueAssignmentsForUser($userId) as $assignment) {
				try {
					$session = $this->sessionMapper->getUserSessionForAssignment($userId, $assignment->getId());
				} catch (DoesNotExistException $e) {
				} catch (MultipleObjectsReturnedException $e) {
				}
			}
		} catch (Exception $e) {

		}
	}
}
