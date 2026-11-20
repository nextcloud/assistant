<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Service\AgentMemoriesService;
use OCA\Assistant\Service\AssistantService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\StorageNotAvailableException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AgentMemoriesServiceTest extends TestCase {

	private AssistantService&MockObject $assistantService;
	private LoggerInterface&MockObject $logger;
	private AgentMemoriesService $service;
	private int $nextFileId = 1;

	protected function setUp(): void {
		parent::setUp();
		$this->assistantService = $this->createMock(AssistantService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new AgentMemoriesService($this->assistantService, $this->logger);
	}

	/**
	 * @param list<Node> $children
	 */
	private function folder(string $name, array $children = []): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getName')->willReturn($name);
		$folder->method('getDirectoryListing')->willReturn($children);
		return $folder;
	}

	private function file(string $name): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($name);
		$file->method('getId')->willReturn($this->nextFileId++);
		return $file;
	}

	private function givenMemoriesFolder(Folder $folder): void {
		$this->assistantService->method('getAssistantDataSubfolder')
			->with('alice', AgentMemoriesService::MEMORIES_FOLDER_PATH)
			->willReturn($folder);
	}

	public function testListMemoriesOfEmptyFolder(): void {
		$this->givenMemoriesFolder($this->folder('Memories'));

		$this->assertSame([], $this->service->listMemories('alice'));
	}

	public function testListMemoriesReturnsMarkdownFilesUpToMaxDepthSortedByPath(): void {
		$general = $this->file('general_notes.md');
		$career = $this->file('career_plans.md');
		$alpha = $this->file('alpha_project.markdown');
		$reading = $this->file('reading_list.md');
		$upperCase = $this->file('upper_case_extension.MD');

		$this->givenMemoriesFolder($this->folder('Memories', [
			$this->folder('work', [
				$career,
				$this->folder('projects', [
					$alpha,
					$this->folder('too', [$this->file('deep.md')]),
				]),
			]),
			$general,
			$this->file('notes.txt'),
			$upperCase,
			$this->folder('Personal', [$reading]),
		]));

		$this->assertSame([
			['path' => 'general_notes.md', 'fileId' => $general->getId()],
			['path' => 'Personal/reading_list.md', 'fileId' => $reading->getId()],
			['path' => 'upper_case_extension.MD', 'fileId' => $upperCase->getId()],
			['path' => 'work/career_plans.md', 'fileId' => $career->getId()],
			['path' => 'work/projects/alpha_project.markdown', 'fileId' => $alpha->getId()],
		], $this->service->listMemories('alice'));
	}

	public function testListMemoriesSkipsUnreadableSubfolder(): void {
		$general = $this->file('general_notes.md');
		$reading = $this->file('reading_list.md');
		$broken = $this->createMock(Folder::class);
		$broken->method('getName')->willReturn('broken');
		$broken->method('getDirectoryListing')->willThrowException(new StorageNotAvailableException());

		$this->givenMemoriesFolder($this->folder('Memories', [
			$broken,
			$general,
			$this->folder('Personal', [$reading]),
		]));
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame([
			['path' => 'general_notes.md', 'fileId' => $general->getId()],
			['path' => 'Personal/reading_list.md', 'fileId' => $reading->getId()],
		], $this->service->listMemories('alice'));
	}

	public function testListMemoriesThrowsWhenMemoriesFolderIsUnreadable(): void {
		$root = $this->createMock(Folder::class);
		$root->method('getDirectoryListing')->willThrowException(new StorageNotAvailableException());
		$this->givenMemoriesFolder($root);

		$this->expectException(StorageNotAvailableException::class);
		$this->service->listMemories('alice');
	}
}
