<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Migration;

use Closure;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\InternalException;
use OCA\Assistant\Service\SessionSummaryService;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;
use Psr\Log\LoggerInterface;

class Version030500Date20260715083046 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
		private IUserManager $userManager,
		private AssignmentsService $assignmentsService,
		private ChatService $chatService,
		private SessionSummaryService $sessionSummaryService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Clean up chat and assignment data left behind by users deleted before UserDeletedListener existed.
	 *
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$userIds = $this->getDistinctUserIds();
		$cleaned = 0;

		foreach ($userIds as $userId) {
			if ($this->userManager->userExists($userId)) {
				continue;
			}

			try {
				$this->assignmentsService->deleteAllForUser($userId);
			} catch (InternalException $e) {
				$this->logger->error('Error while deleting assignments for deleted user ' . $userId, ['exception' => $e]);
			}

			try {
				$this->chatService->deleteAllUserChatData($userId);
			} catch (InternalException $e) {
				$this->logger->error('Error while deleting chat data for deleted user ' . $userId, ['exception' => $e]);
			}

			try {
				$this->sessionSummaryService->deleteSummaryJobsForUser($userId);
			} catch (InternalException $e) {
				$this->logger->error('Error while deleting summary jobs for deleted user ' . $userId, ['exception' => $e]);
			}

			$cleaned++;
		}

		if ($cleaned > 0) {
			$output->info('Cleaned up assistant data for ' . $cleaned . ' deleted user(s)');
		}
	}

	/**
	 * @return list<string>
	 */
	private function getDistinctUserIds(): array {
		$userIds = [];

		$userIdsChat = [];
		$userIdsAssignments = [];

		if ($this->db->tableExists('assistant_chat_sns')) {
			$qb = $this->db->getQueryBuilder();
			$qb->selectDistinct('user_id')
				->from('assistant_chat_sns');
			$result = $qb->executeQuery();
			$userIdsChat = $result->fetchFirstColumn();
			$result->closeCursor();
		}

		if ($this->db->tableExists('assistant_assignments')) {
			$qb = $this->db->getQueryBuilder();
			$qb->selectDistinct('user_id')
				->from('assistant_assignments');
			$result = $qb->executeQuery();
			$userIdsAssignments = $result->fetchFirstColumn();
			$result->closeCursor();
		}

		$userIds = array_unique(array_merge($userIdsChat, $userIdsAssignments));
		return array_values($userIds);
	}
}
