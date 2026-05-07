<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\BackgroundJob\RunAssignmentsJob;
use OCA\Assistant\Db\Assignment;
use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class AssignmentsService {
	public function __construct(
		private AssignmentMapper $assignmentMapper,
		private SessionMapper $sessionMapper,
		private ChatService $chatService,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private IJobList $jobList,
		private IL10N $l10n,
	) {
	}

	/**
	 * @throws InternalException
	 * @throws UnauthorizedException
	 * @throws BadRequestException
	 */
	public function createAssignment(?string $userId, string $prompt, int $startsAt, string $recurrence): Assignment {
		if ($userId === null) {
			throw new UnauthorizedException();
		}
		$now = $this->timeFactory->now()->getTimestamp();
		$assignment = new Assignment();
		$assignment->setUserId($userId);
		$assignment->setPrompt($prompt);
		$assignment->setStartsAt($startsAt);
		$assignment->setLastRunAt(0);
		$assignment->setCreatedAt($now);
		$assignment->setUpdatedAt($now);
		try {
			$assignment->setRecurrence($recurrence);
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestException('Invalid recurrence rule', previous: $e);
		}
		try {
			$this->assignmentMapper->insert($assignment);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		$session = $this->chatService->createChatSession($userId, $this->timeFactory->now()->getTimestamp(), 'Assignment ' . $assignment->getId()); // TODO: Add a proper title here
		$session->setAssignmentId($assignment->getId());
		try {
			$this->sessionMapper->update($session);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if (!$this->jobList->has(RunAssignmentsJob::class, ['userId' => $userId])) {
			$this->jobList->add(RunAssignmentsJob::class, ['userId' => $userId]);
		}
		return $assignment;
	}

	/**
	 * @throws InternalException|UnauthorizedException
	 */
	public function runDueAssignmentsForUser(?string $userId): void {
		if ($userId === null) {
			throw new UnauthorizedException();
		}
		try {
			foreach ($this->assignmentMapper->findDueAssignmentsForUser($userId) as $assignment) {
				if ($assignment === null) {
					continue;
				}
				$this->scheduleAssignmentRun($userId, $assignment->getId());
			}
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function scheduleAssignmentRun(?string $userId, int $assignmentId): void {
		if ($userId === null) {
			throw new UnauthorizedException();
		}
		try {
			try {
				$session = $this->sessionMapper->getUserSessionForAssignment($userId, $assignmentId);
			} catch (DoesNotExistException $e) {
				throw new NotFoundException(previous: $e);
			} catch (MultipleObjectsReturnedException $e) {
				throw new InternalException(previous: $e);
			}
			$assignment = $this->assignmentMapper->find($userId, $assignmentId);
			$assignment->setLastRunAt($this->timeFactory->now()->getTimestamp());
			$this->assignmentMapper->update($assignment);
			$this->chatService->createMessage($userId, $session->getId(), Message::ROLE_HUMAN, $assignment->getPrompt(), $this->timeFactory->now()->getTimestamp());
			$this->chatService->scheduleAssignmentMessageGeneration($userId, $session->getId());
		} catch (BadRequestException|InternalException|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('Error while running assignment ' . $assignmentId . ' for user ' . $userId, ['exception' => $e]);
			if (isset($session)) {
				try {
					$this->chatService->createMessage(
						$userId,
						$session->getId(),
						Message::ROLE_ASSISTANT,
						$this->l10n->t('An error occurred while scheduling this assignment run. Reach out to your system administrator if this issue persists.'),
						$this->timeFactory->now()->getTimestamp()
					);
				} catch (BadRequestException|InternalException|NotFoundException|UnauthorizedException $e) {
					$this->logger->error('Error while creating error message for assignment ' . $assignmentId . ' for user ' . $userId, ['exception' => $e]);
				}
			}
		} catch (NotFoundException $e) {
			try {
				$assignment = $this->assignmentMapper->find($userId, $assignmentId);
				$this->assignmentMapper->delete($assignment);
			} catch (Exception|MultipleObjectsReturnedException $e) {
				$this->logger->error('Error while deleting assignment ' . $assignmentId . ' for user ' . $userId, ['exception' => $e]);
			} catch (DoesNotExistException $e) {
				// pass
			}
		}
	}
}
