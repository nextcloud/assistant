<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Security\ICrypto;
use OCP\Security\IRemoteHostValidator;
use Psr\Log\LoggerInterface;

/**
 * The MCP servers users add for the Context Agent in their personal settings.
 *
 * This app only stores them; the Context Agent connects to them and checks them
 * again before it does. The header values usually hold credentials, so they are
 * stored encrypted and never handed back to the browser, only to the Context
 * Agent through {@see getConnections()}.
 *
 * @psalm-import-type AssistantMcpServer from \OCA\Assistant\ResponseDefinitions
 * @psalm-import-type AssistantMcpConnection from \OCA\Assistant\ResponseDefinitions
 * @psalm-type StoredMcpServer = array{id: string, name: string, url: string, enabled: bool, headers: array<string, string>}
 */
class McpServersService {

	public const ADMIN_ENABLED_KEY = 'mcp_user_servers_enabled';
	public const MAX_SERVERS = 5;
	public const MAX_NAME_LENGTH = 64;
	// The key goes in front of the names of a server's tools, which may be 64 characters long in total
	public const MAX_KEY_LENGTH = 12;
	public const MAX_URL_LENGTH = 2048;
	public const MAX_HEADERS = 10;
	public const MAX_HEADER_VALUE_LENGTH = 8192;

	// Not settable through ConfigController::setConfig, which would skip the checks below
	public const USER_CONFIG_KEY = 'mcp_servers';

	// D, so that $ does not also match before a trailing line feed
	private const HEADER_NAME_PATTERN = '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]{1,256}$/D';
	// Headers that would change how the request is framed or routed, not just authorize it
	private const FORBIDDEN_HEADERS = ['host', 'content-length', 'transfer-encoding', 'connection', 'upgrade', 'te', 'trailer'];

	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private IRemoteHostValidator $remoteHostValidator,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->getValueString(Application::APP_ID, self::ADMIN_ENABLED_KEY, '0', lazy: true) === '1';
	}

	/**
	 * The servers of a user as shown in the settings, without the header values
	 *
	 * @return list<AssistantMcpServer>
	 */
	public function listServers(string $userId): array {
		return array_map(fn (array $server) => $this->toPublic($server), $this->load($userId));
	}

	/**
	 * The enabled servers of a user, with the decrypted header values, for the Context Agent
	 *
	 * @return list<AssistantMcpConnection>
	 */
	public function getConnections(string $userId): array {
		$connections = [];
		foreach ($this->load($userId) as $server) {
			if (!$server['enabled']) {
				continue;
			}
			try {
				$headers = array_map(fn (string $value) => $this->crypto->decrypt($value), $server['headers']);
			} catch (\Throwable $e) {
				// \Throwable, as a hand-edited record can have header values that are not strings
				$this->logger->warning('Could not decrypt the headers of an MCP server, skipping it', ['exception' => $e, 'userId' => $userId]);
				continue;
			}
			$connections[] = ['name' => $server['name'], 'url' => $server['url'], 'headers' => $headers];
		}
		return $connections;
	}

	/**
	 * @param array<string, string> $headers
	 * @return AssistantMcpServer
	 * @throws BadRequestException
	 */
	public function createServer(string $userId, string $name, string $url, array $headers, bool $enabled): array {
		$servers = $this->load($userId);
		if (count($servers) >= self::MAX_SERVERS) {
			throw new BadRequestException($this->l10n->t('You can add at most %d MCP servers', [self::MAX_SERVERS]));
		}
		$server = [
			'id' => bin2hex(random_bytes(8)),
			'name' => $this->validateName($name, $servers),
			'url' => $this->validateUrl($url),
			'enabled' => $enabled,
			'headers' => $this->encryptHeaders($this->validateHeaders($headers, [])),
		];
		$servers[] = $server;
		$this->save($userId, $servers);
		return $this->toPublic($server);
	}

	/**
	 * Change the given fields of a server and keep the others
	 *
	 * The browser never sees the header values, so a header whose value is null keeps
	 * the value stored for it, and a stored header missing from $headers is removed.
	 * Names with a null value are matched case-insensitively and must be stored already.
	 * Stored values are never kept when the URL moves to another host, so they are not
	 * sent somewhere the user did not enter them for.
	 *
	 * @param array<string, ?string>|null $headers
	 * @return AssistantMcpServer
	 * @throws BadRequestException
	 * @throws NotFoundException
	 */
	public function updateServer(string $userId, string $serverId, ?string $name, ?string $url, ?array $headers, ?bool $enabled): array {
		$servers = $this->load($userId);
		$index = $this->findIndex($servers, $serverId);
		$server = $servers[$index];
		$others = array_values(array_filter($servers, fn (array $s) => $s['id'] !== $serverId));
		if ($name !== null) {
			$server['name'] = $this->validateName($name, $others);
		}
		if ($url !== null) {
			$server['url'] = $this->validateUrl($url);
		}
		$originChanged = self::origin($server['url']) !== self::origin($servers[$index]['url']);
		if ($originChanged && $headers === null && $server['headers'] !== []) {
			throw new BadRequestException($this->l10n->t('Enter the header values again when you move the server to another host'));
		}
		if ($headers !== null) {
			$stored = [];
			foreach ($server['headers'] as $storedName => $value) {
				$stored[strtolower((string)$storedName)] = $storedName;
			}
			$kept = [];
			$new = [];
			foreach ($headers as $headerName => $value) {
				if ($value !== null) {
					$new[$headerName] = $value;
					continue;
				}
				if ($originChanged) {
					throw new BadRequestException($this->l10n->t('Enter the header values again when you move the server to another host'));
				}
				// header names are case-insensitive, so match the stored one regardless of case
				$storedName = $stored[strtolower((string)$headerName)] ?? null;
				if ($storedName === null) {
					throw new BadRequestException($this->l10n->t('The header "%s" has no stored value to keep', [(string)$headerName]));
				}
				$kept[$storedName] = $server['headers'][$storedName];
			}
			$server['headers'] = array_merge($kept, $this->encryptHeaders($this->validateHeaders($new, array_keys($kept))));
		}
		if ($enabled !== null) {
			$server['enabled'] = $enabled;
		}
		$servers[$index] = $server;
		$this->save($userId, $servers);
		return $this->toPublic($server);
	}

	/**
	 * @throws NotFoundException
	 */
	public function deleteServer(string $userId, string $serverId): void {
		$servers = $this->load($userId);
		array_splice($servers, $this->findIndex($servers, $serverId), 1);
		$this->save($userId, $servers);
	}

	/**
	 * The name the Context Agent knows a server by, and puts in front of its tool names
	 *
	 * Keep in sync with server_key() in context_agent's ex_app/lib/all_tools/mcp.py.
	 */
	public static function serverKey(string $name): string {
		$key = trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
		return trim(substr($key, 0, self::MAX_KEY_LENGTH), '_');
	}

	/**
	 * The host and port of a URL, the parts that decide who receives the headers
	 */
	private static function origin(string $url): string {
		return strtolower((string)parse_url($url, PHP_URL_HOST)) . ':' . (parse_url($url, PHP_URL_PORT) ?? 443);
	}

	/**
	 * @param list<StoredMcpServer> $others
	 * @throws BadRequestException
	 */
	private function validateName(string $name, array $others): string {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
			throw new BadRequestException($this->l10n->t('The name must be between 1 and %d characters long', [self::MAX_NAME_LENGTH]));
		}
		$key = self::serverKey($name);
		if ($key === '') {
			throw new BadRequestException($this->l10n->t('The name must contain at least one letter or digit'));
		}
		foreach ($others as $other) {
			if (self::serverKey($other['name']) === $key) {
				throw new BadRequestException($this->l10n->t('Another server already has this name or a very similar one'));
			}
		}
		return $name;
	}

	/**
	 * @throws BadRequestException
	 */
	private function validateUrl(string $url): string {
		$url = trim($url);
		$host = parse_url($url, PHP_URL_HOST);
		if (strlen($url) > self::MAX_URL_LENGTH
			|| filter_var($url, FILTER_VALIDATE_URL) === false
			|| strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https'
			|| !is_string($host)) {
			throw new BadRequestException($this->l10n->t('Enter a valid https URL'));
		}
		// The Context Agent checks this again when it connects; this is for early feedback
		if (!$this->remoteHostValidator->isValid($host)) {
			throw new BadRequestException($this->l10n->t('The server must be reachable on the internet, local and internal addresses are not allowed'));
		}
		return $url;
	}

	/**
	 * @param array<array-key, mixed> $headers
	 * @param list<string> $kept names of the stored headers that are kept as they are
	 * @return array<string, string>
	 * @throws BadRequestException
	 */
	private function validateHeaders(array $headers, array $kept): array {
		if (count($headers) + count($kept) > self::MAX_HEADERS) {
			throw new BadRequestException($this->l10n->t('A server can have at most %d headers', [self::MAX_HEADERS]));
		}
		$names = array_map('strtolower', $kept);
		foreach ($headers as $name => $value) {
			if (!is_string($name)
				|| preg_match(self::HEADER_NAME_PATTERN, $name) !== 1
				|| in_array(strtolower($name), self::FORBIDDEN_HEADERS, true)
				|| in_array(strtolower($name), $names, true)) {
				throw new BadRequestException($this->l10n->t('The header name "%s" is not allowed', [(string)$name]));
			}
			if (!is_string($value) || $value === '' || strlen($value) > self::MAX_HEADER_VALUE_LENGTH || preg_match('/[\r\n\0]/', $value) === 1) {
				throw new BadRequestException($this->l10n->t('The value of the header "%s" is not allowed', [$name]));
			}
			$names[] = strtolower($name);
		}
		/** @var array<string, string> $headers */
		return $headers;
	}

	/**
	 * @param array<string, string> $headers
	 * @return array<string, string>
	 */
	private function encryptHeaders(array $headers): array {
		return array_map(fn (string $value) => $this->crypto->encrypt($value), $headers);
	}

	/**
	 * @param StoredMcpServer $server
	 * @return AssistantMcpServer
	 */
	private function toPublic(array $server): array {
		return [
			'id' => $server['id'],
			'name' => $server['name'],
			'url' => $server['url'],
			'enabled' => $server['enabled'],
			'headers' => array_keys($server['headers']),
		];
	}

	/**
	 * @param list<StoredMcpServer> $servers
	 * @throws NotFoundException
	 */
	private function findIndex(array $servers, string $serverId): int {
		foreach ($servers as $index => $server) {
			if ($server['id'] === $serverId) {
				return $index;
			}
		}
		throw new NotFoundException($this->l10n->t('MCP server not found'));
	}

	/**
	 * @return list<StoredMcpServer>
	 */
	private function load(string $userId): array {
		$servers = json_decode($this->config->getUserValue($userId, Application::APP_ID, self::USER_CONFIG_KEY, '[]'), true);
		if (!is_array($servers)) {
			return [];
		}
		$valid = [];
		foreach ($servers as $server) {
			if (is_array($server)
				&& is_string($server['id'] ?? null)
				&& is_string($server['name'] ?? null)
				&& is_string($server['url'] ?? null)
				&& is_bool($server['enabled'] ?? null)
				&& is_array($server['headers'] ?? null)) {
				/** @var StoredMcpServer $server */
				$valid[] = $server;
			}
		}
		return $valid;
	}

	/**
	 * @param list<StoredMcpServer> $servers
	 */
	private function save(string $userId, array $servers): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::USER_CONFIG_KEY, json_encode($servers, JSON_THROW_ON_ERROR));
	}
}
