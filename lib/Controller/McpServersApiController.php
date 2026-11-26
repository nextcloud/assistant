<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\BadRequestException;
use OCA\Assistant\Service\McpServersService;
use OCA\Assistant\Service\NotFoundException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ExAppRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;

/**
 * @psalm-import-type AssistantMcpServer from ResponseDefinitions
 * @psalm-import-type AssistantMcpConnection from ResponseDefinitions
 */
class McpServersApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $l10n,
		private McpServersService $mcpServersService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List the MCP servers of the current user
	 *
	 * Header values are not returned, only the header names.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{enabled: bool, servers: list<AssistantMcpServer>}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: Servers listed; enabled is false if the administrator does not allow users to add MCP servers
	 * 401: User is not authenticated
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['mcp_servers'])]
	public function listServers(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse([
			'enabled' => $this->mcpServersService->isEnabled(),
			'servers' => $this->mcpServersService->listServers($this->userId),
		]);
	}

	/**
	 * Add an MCP server for the current user
	 *
	 * @param string $name Name of the server, shown to the user and used in the names of its tools
	 * @param string $url HTTPS URL of the server's streamable HTTP endpoint
	 * @param array<string, string> $headers HTTP headers to send to the server, for example for authorization
	 * @param bool $enabled Whether the agent may use the server
	 * @return DataResponse<Http::STATUS_CREATED, array{server: AssistantMcpServer}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN, array{error: string}, array{}>
	 *
	 * 201: Server added
	 * 400: Invalid server
	 * 401: User is not authenticated
	 * 403: The administrator does not allow users to add MCP servers
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['mcp_servers'])]
	public function createServer(string $name, string $url, array $headers = [], bool $enabled = true): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->mcpServersService->isEnabled()) {
			return new DataResponse(['error' => $this->l10n->t('Adding MCP servers is not allowed')], Http::STATUS_FORBIDDEN);
		}
		try {
			$server = $this->mcpServersService->createServer($this->userId, $name, $url, $headers, $enabled);
		} catch (BadRequestException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(['server' => $server], Http::STATUS_CREATED);
	}

	/**
	 * Change an MCP server of the current user
	 *
	 * Fields that are not passed are left unchanged.
	 *
	 * @param string $serverId ID of the server
	 * @param string|null $name New name of the server
	 * @param string|null $url New URL of the server
	 * @param array<string, ?string>|null $headers All the headers the server should have afterwards; a null value keeps the stored value of that header
	 * @param bool|null $enabled Whether the agent may use the server
	 * @return DataResponse<Http::STATUS_OK, array{server: AssistantMcpServer}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Server changed
	 * 400: Invalid server
	 * 401: User is not authenticated
	 * 403: The administrator does not allow users to use MCP servers
	 * 404: Server not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['mcp_servers'])]
	public function updateServer(string $serverId, ?string $name = null, ?string $url = null, ?array $headers = null, ?bool $enabled = null): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->mcpServersService->isEnabled()) {
			return new DataResponse(['error' => $this->l10n->t('Your administrator no longer allows MCP servers, you can only remove them')], Http::STATUS_FORBIDDEN);
		}
		try {
			$server = $this->mcpServersService->updateServer($this->userId, $serverId, $name, $url, $headers, $enabled);
		} catch (BadRequestException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse(['server' => $server]);
	}

	/**
	 * Remove an MCP server of the current user
	 *
	 * This is possible even if the administrator no longer allows users to add MCP servers.
	 *
	 * @param string $serverId ID of the server
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Server removed
	 * 401: User is not authenticated
	 * 404: Server not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['mcp_servers'])]
	public function deleteServer(string $serverId): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$this->mcpServersService->deleteServer($this->userId, $serverId);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse([]);
	}

	/**
	 * Get the enabled MCP servers of the current user, with their header values
	 *
	 * Only external apps acting for the user can call this. It is meant for the Context Agent, but any external app
	 * installed by the administrator can call it, as external apps are not told apart here.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{enabled: bool, servers: list<AssistantMcpConnection>}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: Servers returned; enabled is false, and the list empty, if the administrator does not allow users to add MCP servers
	 * 401: User is not authenticated
	 */
	#[ExAppRequired]
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_EX_APP, tags: ['mcp_servers'])]
	public function getConnections(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->mcpServersService->isEnabled()) {
			return new DataResponse(['enabled' => false, 'servers' => []]);
		}
		return new DataResponse([
			'enabled' => true,
			'servers' => $this->mcpServersService->getConnections($this->userId),
		]);
	}
}
