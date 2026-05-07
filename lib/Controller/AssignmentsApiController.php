<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\Db\Assignment;
use OCA\Assistant\Db\AssignmentMapper;
use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\AssignmentsService;
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
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Create a new assignment
	 * @param string $prompt The prompt to be sent to the assistant when the assignment is executed
	 * @param int $startsAt The timestamp when the assignment should start being executed
	 * @param string $recurrence The recurrence rule for the assignment, in RRULE format (e.g. "FREQ=DAILY;INTERVAL=1" for a daily assignment)
	 * @return DataResponse<Http::STATUS_OK, array{assignment: AssistantAssignment}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, '', array{}>
	 *
	 * 200: User assignments returned
	 * 403: User not logged in
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assignments'])]
	#[Http\Attribute\ApiRoute(verb: 'POST', url: '/assignments')]
	public function createUserAssignment(string $prompt, int $startsAt, string $recurrence): DataResponse {
		try {
			$assignment = $this->assignmentsService->createAssignment($this->userId, $prompt, $startsAt, $recurrence);
			$serializedAssignment = $assignment->jsonSerialize();
			return new DataResponse(['assignment' => $serializedAssignment]);
		} catch (InternalException $e) {
			$this->logger->error('Error while fetching assignments for user ' . $this->userId, ['exception' => $e]);
			return new DataResponse('', Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnauthorizedException $e) {
			return new DataResponse('', Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Get user's assignments
	 *
	 * Get a list of assignmetns for the current user.
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
				$assignments = iterator_to_array($this->assignmentMapper->findForUser($this->userId));
				/** @var list<AssistantAssignment> $serializedAssignments */
				$serializedAssignments = array_map(static function (Assignment $assignments) {
					return $assignments->jsonSerialize();
				}, $assignments);
				return new DataResponse(['assignments' => $serializedAssignments]);
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
	 * @return DataResponse<Http::STATUS_OK, array{assignment: AssistantAssignment}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, '', array{}>
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
				/** @var AssistantAssignment $serializedAssignment */
				$serializedAssignment = $assignment->jsonSerialize();
				return new DataResponse(['assignment' => $serializedAssignment]);
			} catch (Exception $e) {
				$this->logger->error('Error while fetching assignment for user ' . $this->userId, ['exception' => $e]);
				return new DataResponse('', HTTP::STATUS_FORBIDDEN);
			} catch (DoesNotExistException|MultipleObjectsReturnedException) {
				return new DataResponse('', HTTP::STATUS_NOT_FOUND);
			}
		}
		return new DataResponse('', HTTP::STATUS_FORBIDDEN);
	}

	/**
	 * Update a user's assignment
	 *
	 * @param int $id The id of the assignment
	 * @param string|null $prompt The prompt to be sent to the assistant when the assignment is executed
	 * @param int|null $startsAt The timestamp when the assignment should start being executed
	 * @param string|null $recurrence The recurrence rule for the assignment, in RRULE format
	 *
	 * @return DataResponse<Http::STATUS_OK, array{assignment: AssistantAssignment}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|HTTP::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, '', array{}>
	 *
	 * 200: User tasks returned
	 * 403: User not logged in
	 * 400: Malformed recurrence rule
	 * 404: Assignment not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assignments'])]
	#[Http\Attribute\ApiRoute(verb: 'PATCH', url: '/assignments/{id}')]
	public function updateUserAssignment(int $id, ?string $prompt, ?string $recurrence, ?int $startsAt): DataResponse {
		if ($this->userId !== null) {
			try {
				$assignment = $this->assignmentMapper->find($this->userId, $id);
				if ($prompt !== null) {
					$assignment->setPrompt($prompt);
				}
				if ($recurrence !== null) {
					try {
						$assignment->setRecurrence($recurrence);
					} catch (\InvalidArgumentException $e) {
						return new DataResponse('', Http::STATUS_BAD_REQUEST);
					}
				}
				if ($startsAt !== null) {
					$assignment->setStartsAt($startsAt);
				}
				$assignment->setUpdatedAt($this->timeFactory->now()->getTimestamp());
				$this->assignmentMapper->update($assignment);
				/** @var AssistantAssignment $serializedAssignment */
				$serializedAssignment = $assignment->jsonSerialize();
				return new DataResponse(['assignment' => $serializedAssignment]);
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
	 * Delete a user's assignment
	 *
	 * @param int $id The id of the assignment to delete
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FORBIDDEN, '', array{}>
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
				return new DataResponse('', HTTP::STATUS_OK);
			} catch (Exception $e) {
				$this->logger->error('Error while fetching assignment for user ' . $this->userId, ['exception' => $e]);
				return new DataResponse('', HTTP::STATUS_FORBIDDEN);
			} catch (DoesNotExistException|MultipleObjectsReturnedException) {
				// 200 OK because of idempotence, if we send DELETE twice, we return the same response twice
				return new DataResponse('', HTTP::STATUS_OK);
			}
		}
		return new DataResponse('', HTTP::STATUS_FORBIDDEN);
	}
}
