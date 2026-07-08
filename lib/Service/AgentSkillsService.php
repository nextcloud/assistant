<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class AgentSkillsService {

	public const GLOBAL_SKILLS_ADMIN_UID_KEY = 'global_skills_admin_uid';
	public const GLOBAL_SKILLS_PATH_KEY = 'global_skills_path';
	public const SKILLS_FOLDER_PATH = 'Context Agent/Skills';

	private const SKILL_FILE_NAME = 'SKILL.md';
	private const FRONTMATTER_DELIMITER = '---';
	private const CACHE_PREFIX = 'assistant_skills';
	private const CACHE_TTL = 24 * 60 * 60;
	private const FRONTMATTER_METADATA_FIELDS = ['name', 'description'];
	private const GLOBAL_CACHE_KEY = 'global_folder';
	private const GLOBAL_SKILL_CACHE_PREFIX = 'global_skill:';

	private ICache $cache;

	public function __construct(
		private AssistantService $assistantService,
		private IRootFolder $rootFolder,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createLocal(self::CACHE_PREFIX);
	}

	/**
	 * List all available skills for a user, returning the parsed metadata (name, description) for each.
	 *
	 * @return list<array{name: string, description: string}>
	 * @throws NotFoundException if the skills folder cannot be resolved
	 * @throws NotPermittedException if the skills folder cannot be created or read
	 * @throws \OC\User\NoUserException if the user does not exist
	 * @throws \OCP\Files\GenericFileException if reading a SKILL.md file fails
	 * @throws \OCP\Lock\LockedException if a SKILL.md file is locked
	 */
	public function listSkills(string $userId): array {
		$skillsFolder = $this->getSkillsFolder($userId);
		$userSkills = $this->listSkillsFromFolder(
			$skillsFolder,
			"folder:$userId",
			"skill:$userId:",
		);

		$globalFolder = $this->getGlobalSkillsFolder();
		if ($globalFolder === null) {
			return array_values($userSkills);
		}
		$globalSkills = $this->listSkillsFromFolder(
			$globalFolder,
			self::GLOBAL_CACHE_KEY,
			self::GLOBAL_SKILL_CACHE_PREFIX,
		);

		// user skills take precedence on name collision
		$merged = $globalSkills;
		foreach ($userSkills as $name => $metadata) {
			$merged[$name] = $metadata;
		}
		return array_values($merged);
	}

	/**
	 * List metadata for all skills directly under the given folder.
	 *
	 * @return array<string, array{name: string, description: string}> indexed by folder/skill name
	 */
	private function listSkillsFromFolder(Folder $folder, string $folderCacheKey, string $skillCacheKeyPrefix): array {
		$folderEtag = $folder->getEtag();
		$cached = $this->cache->get($folderCacheKey);
		if (is_array($cached) && ($cached['etag'] ?? null) === $folderEtag && is_array($cached['skills'] ?? null)) {
			return $cached['skills'];
		}

		$skills = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof Folder) {
				continue;
			}
			try {
				$skillFile = $node->get(self::SKILL_FILE_NAME);
			} catch (NotFoundException $e) {
				$this->logger->debug('Skipping skill folder without ' . self::SKILL_FILE_NAME . ': ' . $node->getName());
				continue;
			}
			if (!$skillFile instanceof File) {
				continue;
			}
			try {
				$skills[$node->getName()] = $this->getSkillMetadata(
					$skillCacheKeyPrefix . md5($node->getName()),
					$skillFile,
				);
			} catch (RuntimeException $e) {
				$this->logger->warning('Failed to read skill metadata for ' . $node->getName() . ': ' . $e->getMessage());
			}
		}

		$this->cache->set($folderCacheKey, ['etag' => $folderEtag, 'skills' => $skills], self::CACHE_TTL);
		return $skills;
	}

	/**
	 * Get a single skill's metadata (name, description), using the per-skill cache keyed on the file etag.
	 *
	 * @return array{name: string, description: string}
	 * @throws RuntimeException if the file has no valid frontmatter or is missing required fields
	 */
	private function getSkillMetadata(string $cacheKey, File $skillFile): array {
		$etag = $skillFile->getEtag();
		$cached = $this->cache->get($cacheKey);
		if (is_array($cached) && ($cached['etag'] ?? null) === $etag && is_array($cached['metadata'] ?? null)) {
			return $cached['metadata'];
		}

		$frontmatter = $this->extractFrontmatter($skillFile);
		$metadata = $this->parseMetadataFields($frontmatter, $skillFile->getPath());
		$this->cache->set($cacheKey, ['etag' => $etag, 'metadata' => $metadata], self::CACHE_TTL);
		return $metadata;
	}

	/**
	 * Parse the required metadata fields (name, description) from a YAML frontmatter string.
	 *
	 * @return array{name: string, description: string}
	 * @throws RuntimeException if the YAML is invalid or any required field is missing
	 */
	public function parseMetadataFields(string $frontmatter, string $filePath): array {
		try {
			$parsed = Yaml::parse($frontmatter);
		} catch (ParseException $e) {
			throw new RuntimeException('Invalid YAML frontmatter in skill file ' . $filePath . ': ' . $e->getMessage(), 0, $e);
		}
		if (!is_array($parsed)) {
			throw new RuntimeException('Skill frontmatter is not a YAML mapping: ' . $filePath);
		}

		$result = [];
		foreach (self::FRONTMATTER_METADATA_FIELDS as $field) {
			$value = $parsed[$field] ?? null;
			if (!is_string($value) || $value === '') {
				throw new RuntimeException('Skill file missing required metadata field "' . $field . '": ' . $filePath);
			}
			$result[$field] = $value;
		}
		return $result;
	}

	/**
	 * Store (create or overwrite) a skill for a user.
	 *
	 * Creates the folder "Skills/$skillName/" and writes a SKILL.md file with a YAML frontmatter
	 * header containing the name and description, followed by the provided body content.
	 *
	 * @param string $userId
	 * @param string $skillName folder name for the skill (must be a valid filesystem path segment)
	 * @param string $description short, agent-facing description of when to use this skill
	 * @param string $content the body of SKILL.md (markdown after the frontmatter)
	 * @return 'created'|'overwritten' 'created' if a new SKILL.md was written, 'overwritten' if an
	 *                                 existing one was replaced
	 * @throws \InvalidArgumentException if the skill name is empty or contains a slash
	 * @throws NotFoundException if the skills folder cannot be resolved
	 * @throws NotPermittedException if the skill folder or SKILL.md file cannot be written, or if a
	 *                               non-folder node already exists at the target skill path
	 * @throws \OC\User\NoUserException if the user does not exist
	 * @throws \OCP\Files\GenericFileException if writing the SKILL.md file fails
	 * @throws \OCP\Lock\LockedException if the SKILL.md file is locked
	 */
	public function storeSkill(string $userId, string $skillName, string $description, string $content): string {
		if ($skillName === '' || str_contains($skillName, '/')) {
			throw new \InvalidArgumentException('Invalid skill name: ' . ($skillName ?: '(empty string)'));
		}
		if ($description === '') {
			throw new \InvalidArgumentException('Skill description must not be empty');
		}

		$skillsFolder = $this->getSkillsFolder($userId);

		$isOverwrite = false;
		if ($skillsFolder->nodeExists($skillName)) {
			$node = $skillsFolder->get($skillName);
			if (!$node instanceof Folder) {
				throw new NotPermittedException('A non-folder node already exists at skill path: ' . $skillName);
			}
			$skillFolder = $node;
			$isOverwrite = $skillFolder->nodeExists(self::SKILL_FILE_NAME);
		} else {
			$skillFolder = $skillsFolder->newFolder($skillName);
		}

		$frontmatter = Yaml::dump([
			'name' => $skillName,
			'description' => $description,
		]);
		$fileContent = self::FRONTMATTER_DELIMITER . "\n"
			. $frontmatter
			. self::FRONTMATTER_DELIMITER . "\n\n"
			. $content;

		if ($isOverwrite) {
			$skillFile = $skillFolder->get(self::SKILL_FILE_NAME);
			if (!$skillFile instanceof File) {
				throw new NotPermittedException('SKILL.md path is not a file: ' . $skillFolder->getPath());
			}
			$skillFile->putContent($fileContent);
		} else {
			$skillFolder->newFile(self::SKILL_FILE_NAME, $fileContent);
		}

		// invalidate caches so the next listSkills/getSkillMetadata call re-reads
		$this->cache->remove("folder:$userId");
		$this->cache->remove('skill:' . $userId . ':' . md5($skillName));

		return $isOverwrite ? 'overwritten' : 'created';
	}

	/**
	 * Load the full content of a skill by its folder/skill name.
	 *
	 * @throws NotFoundException if the skill folder or its SKILL.md file does not exist
	 * @throws NotPermittedException if the skills folder cannot be created or read
	 * @throws \OC\User\NoUserException if the user does not exist
	 * @throws \OCP\Files\GenericFileException if reading the SKILL.md file fails
	 * @throws \OCP\Lock\LockedException if the SKILL.md file is locked
	 */
	public function loadSkill(string $userId, string $skillName): string {
		$userFolder = $this->getSkillsFolder($userId);
		$globalFolder = $this->getGlobalSkillsFolder();

		// user skills take precedence
		$folders = [$userFolder];
		if ($globalFolder !== null) {
			$folders[] = $globalFolder;
		}

		foreach ($folders as $folder) {
			try {
				return $this->loadSkillFromFolder($folder, $skillName);
			} catch (NotFoundException $e) {
				continue;
			}
		}

		throw new NotFoundException('Skill "' . $skillName . '" not found');
	}

	/**
	 * @throws NotFoundException if the skill or its SKILL.md is missing
	 */
	private function loadSkillFromFolder(Folder $folder, string $skillName): string {
		if (!$folder->nodeExists($skillName)) {
			throw new NotFoundException('Skill "' . $skillName . '" not found');
		}
		$skillFolder = $folder->get($skillName);
		if (!$skillFolder instanceof Folder) {
			throw new NotFoundException('Skill "' . $skillName . '" not found');
		}
		$skillFile = $skillFolder->get(self::SKILL_FILE_NAME);
		if (!$skillFile instanceof File) {
			throw new NotFoundException('Skill file for "' . $skillName . '" not found');
		}
		return $skillFile->getContent();
	}

	/**
	 * Extract the YAML frontmatter (the text between the two `---` delimiters) from a SKILL.md file.
	 *
	 * @throws RuntimeException if the file has no valid frontmatter
	 * @throws NotPermittedException if the file cannot be read
	 * @throws \OCP\Files\GenericFileException if reading the file fails
	 * @throws \OCP\Lock\LockedException if the file is locked
	 */
	public function extractFrontmatter(File $file): string {
		$content = $file->getContent();
		$delimiter = self::FRONTMATTER_DELIMITER;

		// must start with the opening delimiter followed by a newline
		if (!str_starts_with($content, $delimiter . "\n") && !str_starts_with($content, $delimiter . "\r\n")) {
			throw new RuntimeException('Skill file missing frontmatter opening delimiter: ' . $file->getPath());
		}

		$offset = strpos($content, "\n") + 1;
		$closingPos = strpos($content, "\n" . $delimiter, $offset);
		if ($closingPos === false) {
			throw new RuntimeException('Skill file missing frontmatter closing delimiter: ' . $file->getPath());
		}

		return substr($content, $offset, $closingPos - $offset);
	}

	/**
	 * Resolve the admin-configured global skills folder, if any.
	 *
	 * Returns null if no folder is configured, the admin user no longer exists, or the
	 * configured path no longer points to a folder.
	 */
	public function getGlobalSkillsFolder(): ?Folder {
		$adminUid = $this->appConfig->getValueString(Application::APP_ID, self::GLOBAL_SKILLS_ADMIN_UID_KEY, '', lazy: true);
		$path = $this->appConfig->getValueString(Application::APP_ID, self::GLOBAL_SKILLS_PATH_KEY, '', lazy: true);
		if ($adminUid === '' || $path === '') {
			return null;
		}
		try {
			$userFolder = $this->rootFolder->getUserFolder($adminUid);
			if (!$userFolder->nodeExists($path)) {
				$this->logger->warning('Global skills folder does not exist: ' . $path . ' (admin: ' . $adminUid . ')');
				return null;
			}
			$node = $userFolder->get($path);
			if (!$node instanceof Folder) {
				$this->logger->warning('Global skills path is not a folder: ' . $path . ' (admin: ' . $adminUid . ')');
				return null;
			}
			return $node;
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to resolve global skills folder', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Set or clear the admin-configured global skills folder. Pass empty strings to clear.
	 */
	public function setGlobalSkillsFolder(string $adminUid, string $path): void {
		$this->appConfig->setValueString(Application::APP_ID, self::GLOBAL_SKILLS_ADMIN_UID_KEY, $adminUid, lazy: true);
		$this->appConfig->setValueString(Application::APP_ID, self::GLOBAL_SKILLS_PATH_KEY, $path, lazy: true);
		// invalidate the global folder cache so the next listSkills re-reads
		$this->cache->remove(self::GLOBAL_CACHE_KEY);
	}

	/**
	 * @return array{admin_uid: string, path: string}
	 */
	public function getGlobalSkillsConfig(): array {
		return [
			'admin_uid' => $this->appConfig->getValueString(Application::APP_ID, self::GLOBAL_SKILLS_ADMIN_UID_KEY, '', lazy: true),
			'path' => $this->appConfig->getValueString(Application::APP_ID, self::GLOBAL_SKILLS_PATH_KEY, '', lazy: true),
		];
	}

	/**
	 * Ensures the skills folder exists at "{Assistant}/Context Agent/Skills" in the user's storage.
	 *
	 * @return Folder
	 * @throws NotFoundException if the user folder or the assistant data folder cannot be resolved
	 * @throws NotPermittedException if the skills folder cannot be created
	 * @throws \OC\User\NoUserException if the user does not exist
	 */
	private function getSkillsFolder(string $userId): Folder {
		$assistantFolder = $this->assistantService->getAssistantDataFolder($userId);
		$skillsFolderPath = self::SKILLS_FOLDER_PATH;

		if ($assistantFolder->nodeExists($skillsFolderPath)) {
			$node = $assistantFolder->get($skillsFolderPath);
			if ($node instanceof Folder) {
				return $node;
			}
		}

		// recursively create the skills folder
		$parentPath = dirname($skillsFolderPath);
		try {
			$assistantFolder->newFolder($parentPath);
		} catch (NotPermittedException $e) {
			if (!$assistantFolder->nodeExists($parentPath)) {
				throw $e;
			}
		}
		return $assistantFolder->newFolder($skillsFolderPath);
	}
}
