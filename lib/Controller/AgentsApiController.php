<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\Db\Agent;
use OCA\Assistant\Service\AgentsService;
use OCA\Assistant\Service\AgentUserException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Throwable;

/**
 * CRUD access to the agents of the "agent as a user" feature (admin only)
 */
class AgentsApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $l10n,
		private AgentsService $agentsService,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return list<array{id: int, user_id: string, display_name: string, personality: ?string, scope: ?string, access_users: list<string>, access_groups: list<string>}>
	 */
	private function serializeAgents(): array {
		$agents = $this->agentsService->getAllAgents();
		$result = [];
		foreach ($agents as $agent) {
			$result[] = $this->serializeAgent($agent);
		}
		return $result;
	}

	private function serializeAgent(Agent $agent): array {
		$user = $this->userManager->get($agent->getUserId());
		$access = $this->agentsService->getAgentAccess((int)$agent->getId());
		return [
			'id' => (int)$agent->getId(),
			'user_id' => $agent->getUserId(),
			'display_name' => $user?->getDisplayName() ?: $agent->getUserId(),
			'personality' => $agent->getPersonality(),
			'scope' => $agent->getScope(),
			'access_users' => $access['users'],
			'access_groups' => $access['groups'],
		];
	}

	private function checkAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		return $this->groupManager->isInGroup($user->getUID(), 'admin');
	}

	/**
	 * @param list<string> $accessUsers
	 * @param list<string> $accessGroups
	 * @return string|null A localized error message, or null when all entries are valid
	 */
	private function validateAccessLists(array $accessUsers, array $accessGroups): ?string {
		foreach ($accessUsers as $accessUserId) {
			if (!is_string($accessUserId) || $this->userManager->get($accessUserId) === null) {
				return $this->l10n->t('The user "%s" does not exist', [$accessUserId]);
			}
		}
		foreach ($accessGroups as $accessGroupId) {
			if (!is_string($accessGroupId) || $this->groupManager->get($accessGroupId) === null) {
				return $this->l10n->t('The group "%s" does not exist', [$accessGroupId]);
			}
		}
		return null;
	}

	/**
	 * List all agents (admin only)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{agents: list<array{id: int, user_id: string, display_name: string, personality: ?string, scope: ?string, access_users: list<string>, access_groups: list<string>}>}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>
	 *
	 * 200: Agents returned
	 * 403: User is not an admin
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['agents'])]
	public function listAgents(): DataResponse {
		if (!$this->checkAdmin()) {
			return new DataResponse(['error' => $this->l10n->t('Only administrators can manage agents')], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(['agents' => $this->serializeAgents()]);
	}

	/**
	 * Create an agent (admin only)
	 *
	 * A dedicated Nextcloud user is created in the default database backend if it does not exist yet.
	 *
	 * @param string $name The name of the agent
	 * @param string $userId The user id of the agent's dedicated Nextcloud user
	 * @param string $personality The agent's system prompt
	 * @param string $scope Optional scope for later use
	 * @param list<string> $accessUsers User ids that may access the agent (empty = every user)
	 * @param list<string> $accessGroups Group ids that may access the agent (empty = every user)
	 * @return DataResponse<Http::STATUS_CREATED, array{agent: array{id: int, user_id: string, display_name: string, personality: ?string, scope: ?string, access_users: list<string>, access_groups: list<string>}}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 201: Agent created
	 * 400: Invalid data
	 * 403: User is not an admin
	 * 500: Agent creation failed
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['agents'])]
	public function createAgent(
		string $name = '', string $userId = '', string $personality = '', string $scope = '',
		array $accessUsers = [], array $accessGroups = [],
	): DataResponse {
		if (!$this->checkAdmin()) {
			return new DataResponse(['error' => $this->l10n->t('Only administrators can manage agents')], Http::STATUS_FORBIDDEN);
		}
		$name = trim($name);
		$userId = trim($userId);
		if ($name === '' || $userId === '') {
			return new DataResponse(['error' => $this->l10n->t('Name and user id are required')], Http::STATUS_BAD_REQUEST);
		}
		if (strlen($personality) > 64_000) {
			return new DataResponse(['error' => $this->l10n->t('The agent personality is too long')], Http::STATUS_BAD_REQUEST);
		}
		$validationError = $this->validateAccessLists($accessUsers, $accessGroups);
		if ($validationError !== null) {
			return new DataResponse(['error' => $validationError], Http::STATUS_BAD_REQUEST);
		}
		try {
			$agent = $this->agentsService->createAgent($name, $userId, $personality, $scope, $accessUsers, $accessGroups);
			return new DataResponse(['agent' => $this->serializeAgent($agent)], Http::STATUS_CREATED);
		} catch (AgentUserException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception|Throwable $e) {
			return new DataResponse(['error' => $this->l10n->t('Failed to create the agent')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update an agent (admin only)
	 *
	 * @param int $id The agent id
	 * @param string|null $personality The new personality (system prompt)
	 * @param string|null $scope The new scope
	 * @param list<string>|null $accessUsers When not null, replaces the user ids that may access the agent
	 * @param list<string>|null $accessGroups When not null, replaces the group ids that may access the agent
	 * @return DataResponse<Http::STATUS_OK, array{agent: array{id: int, user_id: string, display_name: string, personality: ?string, scope: ?string, access_users: list<string>, access_groups: list<string>}}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Agent updated
	 * 400: Invalid data
	 * 403: User is not an admin
	 * 404: Agent not found
	 * 500: Agent update failed
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['agents'])]
	public function updateAgent(
		int $id, ?string $personality = null, ?string $scope = null,
		?array $accessUsers = null, ?array $accessGroups = null,
	): DataResponse {
		if (!$this->checkAdmin()) {
			return new DataResponse(['error' => $this->l10n->t('Only administrators can manage agents')], Http::STATUS_FORBIDDEN);
		}
		$agent = $this->agentsService->getAgentById($id);
		if ($agent === null) {
			return new DataResponse(['error' => $this->l10n->t('Agent not found')], Http::STATUS_NOT_FOUND);
		}
		if ($accessUsers !== null || $accessGroups !== null) {
			$validationError = $this->validateAccessLists($accessUsers ?? [], $accessGroups ?? []);
			if ($validationError !== null) {
				return new DataResponse(['error' => $validationError], Http::STATUS_BAD_REQUEST);
			}
		}
		try {
			$agent = $this->agentsService->updateAgent($id, $personality, $scope, $accessUsers, $accessGroups);
			return new DataResponse(['agent' => $this->serializeAgent($agent)]);
		} catch (AgentUserException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception|Throwable $e) {
			return new DataResponse(['error' => $this->l10n->t('Failed to update the agent')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete an agent (admin only)
	 *
	 * @param int $id The agent id
	 * @param int $deleteUser Whether the related Nextcloud user should be deleted too
	 * @return DataResponse<Http::STATUS_OK, array{action: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Agent deleted
	 * 403: User is not an admin
	 * 404: Agent not found
	 * 500: Agent deletion failed
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['agents'])]
	public function deleteAgent(int $id, int $deleteUser = 0): DataResponse {
		if (!$this->checkAdmin()) {
			return new DataResponse(['error' => $this->l10n->t('Only administrators can manage agents')], Http::STATUS_FORBIDDEN);
		}
		$agent = $this->agentsService->getAgentById($id);
		if ($agent === null) {
			return new DataResponse(['error' => $this->l10n->t('Agent not found')], Http::STATUS_NOT_FOUND);
		}
		try {
			$this->agentsService->deleteAgent($id, $deleteUser === 1);
			return new DataResponse(['action' => 'deleted']);
		} catch (\Exception|Throwable $e) {
			return new DataResponse(['error' => $this->l10n->t('Failed to delete the agent')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
