<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use RuntimeException;

class AgentMemoriesService {

	// must match MEMORIES_RELATIVE_FOLDER_PATH in context_agent
	public const MEMORIES_FOLDER_PATH = 'Context Agent/Memories';
	// must match MAX_MEMORY_FOLDER_DEPTH in context_agent
	private const MAX_FOLDER_DEPTH = 2;
	private const MEMORY_FILE_EXTENSIONS = ['md', 'markdown'];

	public function __construct(
		private AssistantService $assistantService,
	) {
	}

	/**
	 * List the memory files the Context Agent can see for a user, sorted by path.
	 *
	 * @return list<array{path: string, fileId: int}> path is relative to the memories folder
	 * @throws NotFoundException if the memories folder cannot be resolved
	 * @throws NotPermittedException if the memories folder cannot be created or read
	 * @throws RuntimeException if the memories path or its parent exists but is not a folder
	 * @throws \OC\User\NoUserException if the user does not exist
	 */
	public function listMemories(string $userId): array {
		$memories = $this->listMemoriesInFolder($this->getMemoriesFolder($userId), '', 0);
		usort($memories, static fn (array $a, array $b): int => strnatcasecmp($a['path'], $b['path']));
		return $memories;
	}

	/**
	 * @return list<array{path: string, fileId: int}>
	 */
	private function listMemoriesInFolder(Folder $folder, string $prefix, int $depth): array {
		$memories = [];
		foreach ($folder->getDirectoryListing() as $node) {
			$path = $prefix . $node->getName();
			if ($node instanceof Folder) {
				if ($depth < self::MAX_FOLDER_DEPTH) {
					array_push($memories, ...$this->listMemoriesInFolder($node, $path . '/', $depth + 1));
				}
			} elseif ($node instanceof File
				&& in_array(pathinfo($node->getName(), PATHINFO_EXTENSION), self::MEMORY_FILE_EXTENSIONS, true)) {
				$memories[] = ['path' => $path, 'fileId' => $node->getId()];
			}
		}
		return $memories;
	}

	/**
	 * Ensures the memories folder exists at "{Assistant}/Context Agent/Memories" in the user's storage.
	 *
	 * @throws NotFoundException if the user folder or the assistant data folder cannot be resolved
	 * @throws NotPermittedException if the memories folder cannot be created
	 * @throws RuntimeException if the memories path or its parent exists but is not a folder
	 * @throws \OC\User\NoUserException if the user does not exist
	 */
	public function getMemoriesFolder(string $userId): Folder {
		return $this->assistantService->getAssistantDataSubfolder($userId, self::MEMORIES_FOLDER_PATH);
	}
}
