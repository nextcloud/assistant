<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\Db\Assignment;
use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\BadRequestException;
use OCA\Assistant\Service\InternalException;
use OCA\Assistant\Service\UnauthorizedException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type AssistantAssignment from ResponseDefinitions
 */
class AssignmentsApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private AssignmentMapper $assignmentMapper,
		private LoggerInterface $logger,
		private ITimeFactory $timeFactory,
		private AssignmentsService $assignmentsService,
		private SessionMapper $sessionMapper,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Adds the id of each assignment's chat session, which holds the title and the results
	 *
	 * @param list<Assignment> $assignments
	 * @return list<AssistantAssignment>
	 * @throws Exception
	 */
	private function serializeAssignments(string $userId, array $assignments): array {
		$assignmentIds = array_map(static fn (Assignment $assignment): int => $assignment->getId(), $assignments);
		$sessionIds = $this->sessionMapper->getSessionIdsForAssignments($userId, $assignmentIds);
		return array_map(static fn (Assignment $assignment): array => self::serializeAssignment($assignment, $sessionIds[$assignment->getId()] ?? null), $assignments);
	}

	/**
	 * @return AssistantAssignment
	 */
	private static function serializeAssignment(Assignment $assignment, ?int $sessionId): array {
		return array_merge($assignment->jsonSerialize(), ['session_id' => $sessionId]);
	}

	/**
	 * Create a new assignment
	 * @param string $title The title of the new assignment, stored on its chat session without surrounding whitespace. Must not be empty and at most 256 characters long
	 * @param string $prompt The prompt to be sent to the assistant when the assignment is executed
	 * @param int $startsAt The timestamp when the assignment should start being executed
	 * @param string $recurrence The recurrence rule for the assignment, in RRULE format (e.g. "FREQ=DAILY;INTERVAL=1" for a daily assignment)
	 * @param string|null $timezone The timezone for this assignment (either the timezone name or a timezone offset, e.g. "Europe/Berlin" or "+0100" for UTC+1). Defaults to the user's current timezone
	 * @return DataResponse<Http::STATUS_OK, array{assignment: AssistantAssignment}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: User assignments returned
	 * 403: User not logged in
	 * 400: Invalid title, recurrence rule or timezone
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assignments'])]
	#[Http\Attribute\ApiRoute(verb: 'POST', url: '/assignments')]
	public function createUserAssignment(string $title, string $prompt, int $startsAt, string $recurrence, ?string $timezone): DataResponse {
		try {
			['assignment' => $assignment, 'sessionId' => $sessionId] = $this->assignmentsService->createAssignment($this->userId, $title, $prompt, $startsAt, $recurrence, $timezone);
			return new DataResponse(['assignment' => self::serializeAssignment($assignment, $sessionId)]);
		} catch (InternalException $e) {
			$this->logger->error('Error while creating assignment for user ' . $this->userId, ['exception' => $e]);
			return new DataResponse('', Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnauthorizedException $e) {
			return new DataResponse('', Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get user's assignments
	 *
	 * Get a list of assignments for the current user.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{assignments: list<AssistantAssignment>}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, '', array{}>
	 *
	 * 200: User assignments returned
	 * 403: User not logged in
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assignments'])]
	#[Http\Attribute\ApiRoute(verb: 'GET', url: '/assignments')]
	public function getUserAssignments(): DataResponse {
		if ($this->userId !== null) {
			try {
				$assignments = array_values(iterator_to_array($this->assignmentMapper->findForUser($this->userId)));
				return new DataResponse(['assignments' => $this->serializeAssignments($this->userId, $assignments)]);
			} catch (Exception $e) {
				$this->logger->error('Error while fetching assignments for user ' . $this->userId, ['exception' => $e]);
				return new DataResponse('', Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}
		return new DataResponse('', Http::STATUS_FORBIDDEN);
	}

	/**
	 * Get user's assignment
	 *
	 * @param int $id The id of the assignment to return
	 *
	 * @return DataResponse<Http::STATUS_OK, array{assignment: AssistantAssignment}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, '', array{}>
	 *
	 * 200: User tasks returned
	 * 403: User not logged in
	 * 404: Assignment not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assignments'])]
	#[Http\Attribute\ApiRoute(verb: 'GET', url: '/assignments/{id}')]
	public function getUserAssignment(int $id): DataResponse {
		if ($this->userId !== null) {
			try {
				$assignment = $this->assignmentMapper->find($this->userId, $id);
				return new DataResponse(['assignment' => $this->serializeAssignments($this->userId, [$assignment])[0]]);
			} catch (Exception $e) {
				$this->logger->error('Error while fetching assignment for user ' . $this->userId, ['exception' => $e]);
				return new DataResponse('', Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException|MultipleObjectsReturnedException) {
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}
		}
		return new DataResponse('', Http::STATUS_FORBIDDEN);
	}

	/**
	 * Update a user's assignment
	 *
	 * @param int $id The id of the assignment
	 * @param string|null $prompt The prompt to be sent to the assistant when the assignment is executed
	 * @param int|null $startsAt The timestamp when the assignment should start being executed
	 * @param string|null $recurrence The recurrence rule for the assignment, in RRULE format
	 * @param string|null $timezone The timezone for this assignment, omit to leave the current value in place. the value should be either the timezone name or a timezone offset, e.g. "Europe/Berlin" or "+0100" for UTC+1
	 *
	 * @return DataResponse<Http::STATUS_OK, array{assignment: AssistantAssignment}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: User tasks returned
	 * 403: User not logged in
	 * 400: Invalid recurrence rule or timezone
	 * 404: Assignment not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assignments'])]
	#[Http\Attribute\ApiRoute(verb: 'PATCH', url: '/assignments/{id}')]
	public function updateUserAssignment(int $id, ?string $prompt, ?string $recurrence, ?int $startsAt, ?string $timezone): DataResponse {
		if ($this->userId !== null) {
			try {
				$assignment = $this->assignmentMapper->find($this->userId, $id);
				if ($prompt !== null) {
					$assignment->setPrompt($prompt);
				}
				if ($startsAt !== null) {
					$assignment->setStartsAt($startsAt);
				}
				if ($timezone !== null) {
					try {
						$assignment->setTimezone($timezone);
					} catch (\InvalidArgumentException $e) {
						return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
					}
				}
				// set after the start and the timezone, the rule is checked against them.
				// The stored rule is checked again when they change, e.g. a new start after its UNTIL
				if ($recurrence === null && ($startsAt !== null || $timezone !== null)) {
					$recurrence = $assignment->getRecurrence();
				}
				if ($recurrence !== null) {
					try {
						$assignment->setRecurrence($recurrence);
					} catch (\InvalidArgumentException $e) {
						return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
					}
				}
				$assignment->setUpdatedAt($this->timeFactory->now()->getTimestamp());
				// read before the write, so a failed read does not return an error for a saved change
				$serialized = $this->serializeAssignments($this->userId, [$assignment])[0];
				$this->assignmentMapper->update($assignment);
				return new DataResponse(['assignment' => $serialized]);
			} catch (Exception $e) {
				$this->logger->error('Error while updating the assignment for user ' . $this->userId, ['exception' => $e]);
				return new DataResponse('', Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException|MultipleObjectsReturnedException) {
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}
		}
		return new DataResponse('', Http::STATUS_FORBIDDEN);
	}

	/**
	 * Delete a user's assignment
	 *
	 * @param int $id The id of the assignment to delete
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, '', array{}>
	 *
	 * 200: User assignment deleted or not found
	 * 403: User not logged in
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assignments'])]
	#[Http\Attribute\ApiRoute(verb: 'DELETE', url: '/assignments/{id}')]
	public function deleteUserAssignment(int $id): DataResponse {
		if ($this->userId !== null) {
			try {
				$assignment = $this->assignmentMapper->find($this->userId, $id);
				$this->assignmentMapper->delete($assignment);
				return new DataResponse('', Http::STATUS_OK);
			} catch (Exception $e) {
				$this->logger->error('Error while deleting the assignment for user ' . $this->userId, ['exception' => $e]);
				return new DataResponse('', Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException|MultipleObjectsReturnedException) {
				// 200 OK because of idempotence, if we send DELETE twice, we return the same response twice
				return new DataResponse('', Http::STATUS_OK);
			}
		}
		return new DataResponse('', Http::STATUS_FORBIDDEN);
	}
}
