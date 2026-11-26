<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Settings;

use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AgentMemoriesService;
use OCA\Assistant\Service\AgentSkillsService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\McpServersService;
use OCA\Assistant\Settings\Personal;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\Folder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PersonalTest extends TestCase {

	private const USER_ID = 'alice';

	private AgentSkillsService&MockObject $agentSkillsService;
	private AgentMemoriesService&MockObject $agentMemoriesService;
	private ChatService&MockObject $chatService;
	/** @var array<string, mixed> */
	private array $initialState = [];
	private Personal $personal;

	protected function setUp(): void {
		parent::setUp();

		$this->agentSkillsService = $this->createMock(AgentSkillsService::class);
		$this->agentMemoriesService = $this->createMock(AgentMemoriesService::class);
		$this->chatService = $this->createMock(ChatService::class);

		$initialStateService = $this->createMock(IInitialState::class);
		$initialStateService->method('provideInitialState')
			->willReturnCallback(function (string $key, mixed $data): void {
				$this->initialState[$key] = $data;
			});

		$this->personal = new Personal(
			$this->createMock(IConfig::class),
			$this->createMock(IAppConfig::class),
			$initialStateService,
			self::USER_ID,
			$this->createMock(ITaskProcessingManager::class),
			$this->createMock(SessionMapper::class),
			$this->agentSkillsService,
			$this->agentMemoriesService,
			$this->chatService,
			$this->createMock(McpServersService::class),
			$this->createMock(LoggerInterface::class),
		);
	}

	private function folder(int $id): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn($id);
		return $folder;
	}

	public function testAgentContentIsFalseWithoutContextAgent(): void {
		$this->chatService->method('isContextAgentAvailable')->willReturn(false);
		$this->agentSkillsService->expects($this->never())->method('listSkillsBySource');
		$this->agentMemoriesService->expects($this->never())->method('listMemories');

		$this->personal->getForm();

		$this->assertFalse($this->initialState['agentContent']);
	}

	public function testAgentContentListsSkillsAndMemories(): void {
		$skills = [
			'user' => [['name' => 'mine', 'description' => 'My skill', 'fileId' => 11]],
			'global' => [['name' => 'shared', 'description' => 'Shared skill']],
		];
		$memories = [['path' => 'work/plans.md', 'fileId' => 21]];

		$this->chatService->method('isContextAgentAvailable')->willReturn(true);
		$this->agentSkillsService->method('listSkillsBySource')->with(self::USER_ID)->willReturn($skills);
		$this->agentSkillsService->method('getSkillsFolder')->with(self::USER_ID)->willReturn($this->folder(10));
		$this->agentMemoriesService->method('listMemories')->with(self::USER_ID)->willReturn($memories);
		$this->agentMemoriesService->method('getMemoriesFolder')->with(self::USER_ID)->willReturn($this->folder(20));

		$this->personal->getForm();

		$this->assertSame([
			'skills' => $skills,
			'skillsFolderId' => 10,
			'memories' => $memories,
			'memoriesFolderId' => 20,
		], $this->initialState['agentContent']);
	}

	public function testAgentContentKeepsMemoriesWhenSkillsFail(): void {
		$memories = [['path' => 'notes.md', 'fileId' => 21]];

		$this->chatService->method('isContextAgentAvailable')->willReturn(true);
		$this->agentSkillsService->method('listSkillsBySource')->willThrowException(new \RuntimeException('broken'));
		$this->agentMemoriesService->method('listMemories')->willReturn($memories);
		$this->agentMemoriesService->method('getMemoriesFolder')->willReturn($this->folder(20));

		$this->personal->getForm();

		$this->assertSame([
			'skills' => null,
			'skillsFolderId' => null,
			'memories' => $memories,
			'memoriesFolderId' => 20,
		], $this->initialState['agentContent']);
	}

	public function testAgentContentKeepsSkillsWhenMemoriesFail(): void {
		$skills = ['user' => [], 'global' => [['name' => 'shared', 'description' => 'Shared skill']]];

		$this->chatService->method('isContextAgentAvailable')->willReturn(true);
		$this->agentSkillsService->method('listSkillsBySource')->willReturn($skills);
		$this->agentSkillsService->method('getSkillsFolder')->willReturn($this->folder(10));
		$this->agentMemoriesService->method('listMemories')->willThrowException(new \RuntimeException('broken'));

		$this->personal->getForm();

		$this->assertSame([
			'skills' => $skills,
			'skillsFolderId' => 10,
			'memories' => null,
			'memoriesFolderId' => null,
		], $this->initialState['agentContent']);
	}
}
