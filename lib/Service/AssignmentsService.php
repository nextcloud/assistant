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
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\IDateTimeZone;
use OCP\IUserManager;
use OCP\TaskProcessing\Exception\Exception as TaskProcessingException;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use Psr\Log\LoggerInterface;

class AssignmentsService {
	public function __construct(
		private AssignmentMapper $assignmentMapper,
		private SessionMapper $sessionMapper,
		private ChatService $chatService,
		private AgentTalkService $agentTalkService,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private IJobList $jobList,
		private IDateTimeZone $dateTimeZone,
		private IUserManager $userManager,
		private ITaskProcessingManager $taskProcessingManager,
	) {
	}

	/**
	 * Create a new assignment
	 *
	 * @param int|null $originTaskId The id of the TaskProcessing task during which the
	 *                               assignment is created by an agent; null when the
	 *                               assignment is created directly by a human
	 * @throws InternalException
	 * @throws UnauthorizedException
	 * @throws BadRequestException
	 */
	public function createAssignment(?string $userId, string $title, string $prompt, int $startsAt, string $recurrence, ?string $timezone, ?int $originTaskId = null): Assignment {
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
		if ($originTaskId !== null) {
			$agentSession = $this->getAgentSessionForOriginTask($userId, $originTaskId);
			if ($agentSession !== null) {
				// the assignment was ordered through the agent in Talk: record the
				// requester right away, so that the run results are delivered in
				// the requester's Talk thread even if the creating run dies before
				// finishing. The assignment keeps being owned and run by the agent
				// user; the task's Talk thread itself is opened by its first run.
				$assignment->setRequesterUserId($agentSession->getRequesterUserId());
			} else {
				// without the requester, the task silently degrades to the
				// previous state: the results only reach the agent's web UI
				$this->logger->warning('Could not resolve the requester of a scheduled task created by an agent', [
					'originTaskId' => $originTaskId,
					'agentUserId' => $userId,
				]);
			}
		}
		if ($timezone === null) {
			$timezone = $this->dateTimeZone->getTimeZone(userId: $userId)->getName();
		}
		try {
			$assignment->setTimezone($timezone);
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestException($e->getMessage(), previous: $e);
		}
		// set after the start and the timezone, the rule is checked against them
		try {
			$assignment->setRecurrence($recurrence);
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestException($e->getMessage(), previous: $e);
		}
		try {
			$this->assignmentMapper->insert($assignment);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		$session = $this->chatService->createChatSession($userId, title: $title);
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
	 * @throws InternalException
	 */
	public function deleteAllForUser(string $userId): void {
		try {
			$this->assignmentMapper->deleteAllForUser($userId);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
		if ($this->jobList->has(RunAssignmentsJob::class, ['userId' => $userId])) {
			$this->jobList->remove(RunAssignmentsJob::class, ['userId' => $userId]);
		}
	}

	/**
	 * @throws InternalException|UnauthorizedException
	 */
	public function runDueAssignmentsForUser(?string $userId): void {
		if ($userId === null) {
			throw new UnauthorizedException();
		}
		if ($this->userManager->get($userId) === null) {
			$this->deleteAllForUser($userId);
			return;
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
			if ($session->getAgencyPendingActions() !== null) {
				$this->logger->debug('Skipping assignment run as the last run still has pending actions');
				return;
			}
			$assignment = $this->assignmentMapper->find($userId, $assignmentId);
			$assignment->setLastRunAt($this->timeFactory->now()->getTimestamp());
			$this->assignmentMapper->update($assignment);
			if ($assignment->getRequesterUserId() !== null) {
				// the assignment was ordered through the agent in Talk: stamp the
				// run session so the Talk reply path is triggered and the run
				// output is delivered in the task's Talk thread. The 1-1 room
				// itself is derived from the requester, only the thread is taken
				// from the assignment.
				$session->setRequesterUserId($assignment->getRequesterUserId());
				$session->setThreadId($assignment->getTalkThreadId());
				$this->sessionMapper->update($session);
				// the silent marker stands in the thread while the run is being
				// generated; the output replaces it in place
				$this->agentTalkService->postAssignmentRunMarker(
					$session,
					$assignment->getLastRunAt(),
					$assignment->getRequesterUserId(),
					$this->getScheduledTaskThreadTitle($assignment),
				);
			}
			$this->chatService->createMessage($userId, $session->getId(), Message::ROLE_HUMAN, $assignment->getPrompt());
			$this->chatService->scheduleAssignmentMessageGeneration($userId, $session->getId());
		} catch (BadRequestException|InternalException|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('Error while running assignment ' . $assignmentId . ' for user ' . $userId, ['exception' => $e]);
			if (isset($session) && $session->getRequesterUserId() !== null) {
				// the generation was never scheduled: no task failed event will
				// fire, so the run marker must be turned into a failure notice here
				try {
					$this->agentTalkService->markGenerationFailed($userId, $session->getId());
				} catch (\Throwable $e2) {
					$this->logger->error('Failed to post the failure notice of an assignment run', ['exception' => $e2]);
				}
			}
			if (isset($session)) {
				try {
					// the error notice is read by the requester for tasks
					// ordered through an agent, by the assignment owner otherwise
					$noticeUserId = isset($assignment)
						? ($assignment->getRequesterUserId() ?? (string)$assignment->getUserId())
						: $userId;
					$this->chatService->createMessage(
						$userId,
						$session->getId(),
						Message::ROLE_ASSISTANT,
						$this->agentTalkService->getUserL10n($noticeUserId)->t('An error occurred while scheduling this assignment run. Reach out to your system administrator if this issue persists.'),
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

	/**
	 * Get the agent session in which the given TaskProcessing task runs, when
	 * the task is an agency interaction task of the given agent user with a
	 * requester. Used to find the requester of scheduled tasks created by an
	 * agent run: the run passes the id of the task it runs in as originTaskId.
	 *
	 * The session is resolved from the task itself, so no identity crosses the
	 * API boundary: the task custom id of chat interactions carries the session
	 * id, and the input of voice interaction sub-tasks carries the conversation
	 * token stored on the agent session when they were scheduled.
	 *
	 * @param string $userId The user creating the assignment: the agent user that runs the task
	 * @param int $originTaskId The id of the TaskProcessing task the agent run executes
	 * @return Session|null The agent session with the requester user id set,
	 *                      null when the task cannot be trusted or linked to a requester
	 */
	private function getAgentSessionForOriginTask(string $userId, int $originTaskId): ?Session {
		try {
			$task = $this->taskProcessingManager->getTask($originTaskId);
		} catch (TaskProcessingException $e) {
			$this->logger->warning('Could not find the origin task of a scheduled task created by an agent', [
				'originTaskId' => $originTaskId,
				'exception' => $e,
			]);
			return null;
		}
		if ($task->getUserId() !== $userId) {
			$this->logger->warning('Ignoring an origin task that does not belong to the user creating a scheduled task', [
				'originTaskId' => $originTaskId,
				'agentUserId' => $userId,
				'taskUserId' => $task->getUserId(),
			]);
			return null;
		}
		if (!$this->isAgencyInteractionTaskType($task->getTaskTypeId())) {
			return null;
		}

		$sessionId = null;
		if (preg_match('/^chatty-llm:(\d+)$/', (string)$task->getCustomId(), $matches)) {
			$sessionId = (int)$matches[1];
		} else {
			// voice interactions run as an agency sub-task without a custom id
			$conversationToken = $task->getInput()['conversation_token'] ?? null;
			if (is_string($conversationToken) && $conversationToken !== '') {
				try {
					$sessionId = $this->sessionMapper->getAgentSessionByAgencyConversationToken($userId, $conversationToken)->getId();
				} catch (Exception|DoesNotExistException|MultipleObjectsReturnedException $e) {
					$this->logger->warning('Could not find the agent session of a voice interaction that created a scheduled task', [
						'originTaskId' => $originTaskId,
						'exception' => $e,
					]);
					return null;
				}
			}
		}
		if ($sessionId === null) {
			return null;
		}
		try {
			$session = $this->sessionMapper->getUserSession($userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			$this->logger->warning('Ignoring an origin task of a session that does not belong to its user', [
				'originTaskId' => $originTaskId,
				'agentUserId' => $userId,
				'sessionId' => $sessionId,
			]);
			return null;
		}
		if ($session->getRequesterUserId() === null) {
			// a regular chat session without a requester: nothing to link
			return null;
		}
		return $session;
	}

	/**
	 * @param string $taskTypeId
	 * @return bool Whether the task type is one of the agency interaction task types
	 */
	private function isAgencyInteractionTaskType(string $taskTypeId): bool {
		$agencyTaskTypeIds = [
			class_exists(\OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::class)
				? \OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID
				: null,
			class_exists(\OCP\TaskProcessing\TaskTypes\MultimodalContextAgentInteraction::class)
				? \OCP\TaskProcessing\TaskTypes\MultimodalContextAgentInteraction::ID
				: null,
		];
		return in_array($taskTypeId, array_filter($agencyTaskTypeIds), true);
	}

	/**
	 * Store the Talk thread of the agent's 1-1 conversation on the given
	 * assignments: the thread the run results are delivered in. Called right
	 * after the reply that created the thread, and after each run reply so
	 * that a recreated thread is stored again.
	 *
	 * @param Session $session The session whose Talk thread is the delivery surface
	 * @param list<int> $assignmentIds
	 * @return void
	 */
	public function storeScheduledTaskThread(Session $session, array $assignmentIds): void {
		$threadId = $session->getThreadId();
		if ($threadId === null) {
			return;
		}
		foreach ($assignmentIds as $assignmentId) {
			if ($assignmentId === null) {
				continue;
			}
			try {
				$assignment = $this->assignmentMapper->find($session->getUserId(), $assignmentId);
			} catch (Exception|DoesNotExistException|MultipleObjectsReturnedException $e) {
				$this->logger->warning('Could not store the Talk thread of a scheduled task', [
					'assignmentId' => $assignmentId,
					'exception' => $e,
				]);
				continue;
			}
			if ($assignment->getTalkThreadId() === $threadId) {
				continue;
			}
			$assignment->setTalkThreadId($threadId);
			try {
				$this->assignmentMapper->update($assignment);
			} catch (Exception $e) {
				// without the thread, the run results are posted as plain
				// messages of the 1-1 conversation instead
				$this->logger->error('Could not store the Talk thread of a scheduled task', [
					'assignmentId' => $assignmentId,
					'exception' => $e,
				]);
			}
		}
	}

	/**
	 * The title of the Talk thread that carries the run results of a scheduled
	 * task: "Scheduled task - <name>", like the agent session titles. Falls
	 * back to "Scheduled task" when the chat session holding the name is gone.
	 * Rendered in the language of the user who reads it in Talk: the requester
	 * for tasks ordered through an agent, the assignment owner otherwise.
	 */
	private function getScheduledTaskThreadTitle(Assignment $assignment): string {
		$title = null;
		try {
			$session = $this->sessionMapper->getUserSessionForAssignment($assignment->getUserId(), $assignment->getId());
			$title = $session->getTitle();
		} catch (Exception|DoesNotExistException|MultipleObjectsReturnedException $e) {
			// the session of the assignment may be gone
		}
		$l10n = $this->agentTalkService->getUserL10n($assignment->getRequesterUserId() ?? (string)$assignment->getUserId());
		if ($title === null || trim($title) === '') {
			return $l10n->t('Scheduled task');
		}
		return $l10n->t('Scheduled task') . AgentTalkService::THREAD_TITLE_SEPARATOR . trim($title);
	}
}
