<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Service\BadRequestException;
use OCA\Assistant\Service\McpServersService;
use OCA\Assistant\Service\NotFoundException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Security\ICrypto;
use OCP\Security\IRemoteHostValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class McpServersServiceTest extends TestCase {

	/** @var array<string, string> user config of the test user, by key */
	private array $userConfig = [];
	private IRemoteHostValidator&MockObject $remoteHostValidator;
	private McpServersService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->userConfig = [];
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')
			->willReturnCallback(fn (string $userId, string $appId, string $key, string $default) => $this->userConfig[$key] ?? $default);
		$config->method('setUserValue')
			->willReturnCallback(function (string $userId, string $appId, string $key, string $value): void {
				$this->userConfig[$key] = $value;
			});

		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(fn (string $value) => 'enc:' . strrev($value));
		$crypto->method('decrypt')->willReturnCallback(fn (string $value) => strrev(substr($value, 4)));

		$this->remoteHostValidator = $this->createMock(IRemoteHostValidator::class);
		$this->remoteHostValidator->method('isValid')
			->willReturnCallback(fn (string $host) => !in_array($host, ['localhost', '10.0.0.1'], true));

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $parameters = []) => vsprintf($text, $parameters));

		$this->service = new McpServersService(
			$config,
			$this->createMock(IAppConfig::class),
			$crypto,
			$this->remoteHostValidator,
			$l10n,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testHeaderValuesAreEncryptedAndOnlyHandedToTheAgent(): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', ['Authorization' => 'Bearer secret'], true);

		$this->assertSame(['Authorization'], $server['headers']);
		$this->assertStringNotContainsString('Bearer secret', $this->userConfig[McpServersService::USER_CONFIG_KEY]);
		$this->assertSame([$server], $this->service->listServers('alice'));
		$this->assertSame(
			[['name' => 'Weather', 'url' => 'https://weather.example/mcp', 'headers' => ['Authorization' => 'Bearer secret']]],
			$this->service->getConnections('alice'),
		);
	}

	public function testDisabledServersAreNotHandedToTheAgent(): void {
		$this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', [], false);
		$this->assertSame([], $this->service->getConnections('alice'));
	}

	public function testUpdateKeepsStoredHeaderValuesThatAreNotResent(): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', ['Authorization' => 'Bearer secret', 'X-Old' => 'old'], true);

		$this->service->updateServer('alice', $server['id'], null, null, ['Authorization' => null, 'X-New' => 'new'], null);

		$this->assertSame(
			['Authorization' => 'Bearer secret', 'X-New' => 'new'],
			$this->service->getConnections('alice')[0]['headers'],
		);
	}

	public function testUpdateKeepsStoredHeaderValuesRegardlessOfCase(): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', ['Authorization' => 'Bearer secret'], true);

		$this->service->updateServer('alice', $server['id'], null, null, ['authorization' => null], null);

		$this->assertSame(['Authorization' => 'Bearer secret'], $this->service->getConnections('alice')[0]['headers']);
	}

	public function testUpdateRefusesToKeepHeadersThatAreNotStored(): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', [], true);

		$this->expectException(BadRequestException::class);
		$this->service->updateServer('alice', $server['id'], null, null, ['X-Missing' => null], null);
	}

	public function testUpdateKeepsStoredHeaderValuesWhenTheUrlStaysOnTheSameHost(): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', ['Authorization' => 'Bearer secret'], true);

		$this->service->updateServer('alice', $server['id'], null, 'https://WEATHER.example:443/v2/mcp', ['Authorization' => null], null);

		$this->assertSame(['Authorization' => 'Bearer secret'], $this->service->getConnections('alice')[0]['headers']);
	}

	public static function dataUpdatesMovingStoredHeadersToAnotherHost(): array {
		return [
			'other host, headers kept' => ['https://other.example/mcp', ['Authorization' => null]],
			'other host, headers not sent' => ['https://other.example/mcp', null],
			'other port' => ['https://weather.example:8443/mcp', ['Authorization' => null]],
		];
	}

	/**
	 * @dataProvider dataUpdatesMovingStoredHeadersToAnotherHost
	 */
	public function testUpdateRefusesToMoveStoredHeaderValuesToAnotherHost(string $url, ?array $headers): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', ['Authorization' => 'Bearer secret'], true);

		$this->expectException(BadRequestException::class);
		$this->service->updateServer('alice', $server['id'], null, $url, $headers, null);
	}

	public function testUpdateAcceptsNewHeaderValuesForAnotherHost(): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', ['Authorization' => 'Bearer secret'], true);

		$this->service->updateServer('alice', $server['id'], null, 'https://other.example/mcp', ['Authorization' => 'Bearer other'], null);

		$this->assertSame(['Authorization' => 'Bearer other'], $this->service->getConnections('alice')[0]['headers']);
	}

	public function testUndecryptableServersAreSkipped(): void {
		$this->userConfig[McpServersService::USER_CONFIG_KEY] = json_encode([
			['id' => 'a', 'name' => 'Broken', 'url' => 'https://broken.example/mcp', 'enabled' => true, 'headers' => ['X-A' => ['not a string']]],
		]);
		$this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', [], true);

		$this->assertSame(['Weather'], array_column($this->service->getConnections('alice'), 'name'));
	}

	public function testDelete(): void {
		$server = $this->service->createServer('alice', 'Weather', 'https://weather.example/mcp', [], true);
		$this->service->deleteServer('alice', $server['id']);
		$this->assertSame([], $this->service->listServers('alice'));

		$this->expectException(NotFoundException::class);
		$this->service->deleteServer('alice', $server['id']);
	}

	public static function dataInvalidServers(): array {
		return [
			'no name' => ['  ', 'https://weather.example/mcp', []],
			'name without letters or digits' => ['!!!', 'https://weather.example/mcp', []],
			'plain http' => ['Weather', 'http://weather.example/mcp', []],
			'not a URL' => ['Weather', 'weather', []],
			'local host' => ['Weather', 'https://localhost/mcp', []],
			'internal address' => ['Weather', 'https://10.0.0.1/mcp', []],
			'host header' => ['Weather', 'https://weather.example/mcp', ['Host' => 'internal']],
			'header injection' => ['Weather', 'https://weather.example/mcp', ['X-A' => "b\r\nX-B: c"]],
			'invalid header name' => ['Weather', 'https://weather.example/mcp', ['Bad Name' => 'b']],
			'header name with trailing line feed' => ['Weather', 'https://weather.example/mcp', ["X-Trace\n" => 'b']],
			'empty header value' => ['Weather', 'https://weather.example/mcp', ['X-A' => '']],
		];
	}

	/**
	 * @dataProvider dataInvalidServers
	 */
	public function testInvalidServersAreRefused(string $name, string $url, array $headers): void {
		$this->expectException(BadRequestException::class);
		$this->service->createServer('alice', $name, $url, $headers, true);
	}

	public function testNamesMustGiveDistinctKeys(): void {
		$this->service->createServer('alice', 'My Weather', 'https://weather.example/mcp', [], true);

		$this->expectException(BadRequestException::class);
		$this->service->createServer('alice', 'my-weather', 'https://other.example/mcp', [], true);
	}

	public function testRenamingAServerToItsOwnKeyIsAllowed(): void {
		$server = $this->service->createServer('alice', 'My Weather', 'https://weather.example/mcp', [], true);
		$this->assertSame('my weather', $this->service->updateServer('alice', $server['id'], 'my weather', null, null, null)['name']);
	}

	public function testNumberOfServersIsLimited(): void {
		for ($i = 0; $i < McpServersService::MAX_SERVERS; $i++) {
			$this->service->createServer('alice', 'Server ' . $i, 'https://weather.example/mcp', [], true);
		}

		$this->expectException(BadRequestException::class);
		$this->service->createServer('alice', 'One too many', 'https://weather.example/mcp', [], true);
	}

	public function testServerKeyMatchesTheContextAgent(): void {
		// the same cases as server_key() in context_agent's tests
		$this->assertSame('my_weather', McpServersService::serverKey('My Weather!'));
		$this->assertSame('wea_ther', McpServersService::serverKey('Wea-ther'));
		$this->assertSame('', McpServersService::serverKey('!!!'));
		$this->assertSame('a_very_long_server_n', McpServersService::serverKey('A very long server name indeed'));
	}
}
