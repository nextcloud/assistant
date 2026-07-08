<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\AgentSkillsService;
use OCA\Assistant\Service\AssistantService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Test\TestCase;

/**
 * Integration tests for AgentSkillsService.
 *
 * Runs against a real Nextcloud test server with a real filesystem and database.
 * Creates dedicated test users and cleans up all written files in tearDown.
 *
 * @group DB
 */
class AgentSkillsServiceTest extends TestCase {

	private const TEST_USER = 'skills_test_user';
	private const ADMIN_USER = 'skills_test_admin';
	/** Folder inside the admin user folder used as the global skills pool (separate from the admin's personal skills) */
	private const GLOBAL_POOL_FOLDER = 'global-skills-pool';

	private AgentSkillsService $service;
	private AssistantService $assistantService;
	private IRootFolder $rootFolder;
	private IAppConfig $appConfig;
	private LoggerInterface $logger;
	/** @var list<IUser> */
	private array $createdUsers = [];

	protected function setUp(): void {
		parent::setUp();

		/** @var IUserManager $userManager */
		$userManager = Server::get(IUserManager::class);
		foreach ([self::TEST_USER, self::ADMIN_USER] as $uid) {
			if (!$userManager->userExists($uid)) {
				$this->createdUsers[] = $userManager->createUser($uid, $uid . '_password123');
			}
		}

		$this->rootFolder = Server::get(IRootFolder::class);
		$this->appConfig = Server::get(IAppConfig::class);
		$this->logger = Server::get(LoggerInterface::class);
		$this->assistantService = Server::get(AssistantService::class);

		$this->service = new AgentSkillsService(
			$this->assistantService,
			$this->rootFolder,
			$this->appConfig,
			$this->logger,
			Server::get(ICacheFactory::class),
		);

		$this->appConfig->deleteKey(Application::APP_ID, AgentSkillsService::GLOBAL_SKILLS_ADMIN_UID_KEY);
		$this->appConfig->deleteKey(Application::APP_ID, AgentSkillsService::GLOBAL_SKILLS_PATH_KEY);
	}

	protected function tearDown(): void {
		foreach ($this->createdUsers as $user) {
			try {
				$userFolder = $this->rootFolder->getUserFolder($user->getUID());
				$skillsPath = $this->getSkillsPath($user->getUID());
				if ($userFolder->nodeExists($skillsPath)) {
					$userFolder->get($skillsPath)->delete();
				}
				if ($user->getUID() === self::ADMIN_USER && $userFolder->nodeExists(self::GLOBAL_POOL_FOLDER)) {
					$userFolder->get(self::GLOBAL_POOL_FOLDER)->delete();
				}
			} catch (\Throwable $e) {
				$this->logger->error('AgentSkillsServiceTest tearDown: failed to delete skills folder for ' . $user->getUID(), ['exception' => $e]);
			}

			try {
				$user->delete();
			} catch (\Throwable $e) {
				$this->logger->error('AgentSkillsServiceTest tearDown: failed to delete user ' . $user->getUID(), ['exception' => $e]);
			}
		}
		$this->createdUsers = [];

		$this->appConfig->deleteKey(Application::APP_ID, AgentSkillsService::GLOBAL_SKILLS_ADMIN_UID_KEY);
		$this->appConfig->deleteKey(Application::APP_ID, AgentSkillsService::GLOBAL_SKILLS_PATH_KEY);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function getSkillsPath(string $uid): string {
		$dataFolder = $this->assistantService->getAssistantDataFolder($uid);
		return $dataFolder->getName() . '/' . AgentSkillsService::SKILLS_FOLDER_PATH;
	}

	/**
	 * Write a properly formatted SKILL.md directly into the global pool folder,
	 * bypassing storeSkill so that the admin's personal skills folder stays separate.
	 */
	private function storeGlobalSkill(string $skillName, string $description, string $body): void {
		$adminFolder = $this->rootFolder->getUserFolder(self::ADMIN_USER);
		if (!$adminFolder->nodeExists(self::GLOBAL_POOL_FOLDER)) {
			$adminFolder->newFolder(self::GLOBAL_POOL_FOLDER);
		}
		/** @var Folder $pool */
		$pool = $adminFolder->get(self::GLOBAL_POOL_FOLDER);
		if (!$pool->nodeExists($skillName)) {
			$pool->newFolder($skillName);
		}
		/** @var Folder $skillFolder */
		$skillFolder = $pool->get($skillName);
		$content = "---\nname: $skillName\ndescription: $description\n---\n\n$body";
		if ($skillFolder->nodeExists('SKILL.md')) {
			/** @var File $file */
			$file = $skillFolder->get('SKILL.md');
			$file->putContent($content);
		} else {
			$skillFolder->newFile('SKILL.md', $content);
		}
	}

	/**
	 * Write a raw SKILL.md under the user's skills folder without going through storeSkill,
	 * so we can test malformed content.
	 */
	private function writeRawSkillFile(string $uid, string $skillName, string $rawContent): File {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		$dirPath = $this->getSkillsPath($uid) . '/' . $skillName;
		$parts = explode('/', $dirPath);
		$node = $userFolder;
		foreach ($parts as $part) {
			if (!$node->nodeExists($part)) {
				$node = $node->newFolder($part);
			} else {
				$node = $node->get($part);
			}
		}
		if ($node->nodeExists('SKILL.md')) {
			/** @var File $file */
			$file = $node->get('SKILL.md');
			$file->putContent($rawContent);
			return $file;
		}
		return $node->newFile('SKILL.md', $rawContent);
	}

	// -------------------------------------------------------------------------
	// extractFrontmatter – real File node
	// -------------------------------------------------------------------------

	public function testExtractFrontmatterValid(): void {
		$raw = "---\nname: My Skill\ndescription: Does something\n---\n\n# Body";
		$file = $this->writeRawSkillFile(self::TEST_USER, 'my-skill', $raw);

		$result = $this->service->extractFrontmatter($file);

		$this->assertSame("name: My Skill\ndescription: Does something", $result);
	}

	public function testExtractFrontmatterMissingOpeningDelimiter(): void {
		$file = $this->writeRawSkillFile(self::TEST_USER, 'bad-skill', "name: My Skill\n---\n\n# Body");

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/missing frontmatter opening delimiter/');
		$this->service->extractFrontmatter($file);
	}

	public function testExtractFrontmatterMissingClosingDelimiter(): void {
		$file = $this->writeRawSkillFile(self::TEST_USER, 'bad-skill-2', "---\nname: My Skill\n\n# No closing");

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/missing frontmatter closing delimiter/');
		$this->service->extractFrontmatter($file);
	}

	// -------------------------------------------------------------------------
	// parseMetadataFields – pure logic, no filesystem needed
	// -------------------------------------------------------------------------

	public function testParseMetadataFieldsValid(): void {
		$result = $this->service->parseMetadataFields(
			"name: Cool Skill\ndescription: Does cool things\n",
			'/some/path/to/SKILL.md',
		);

		$this->assertSame(['name' => 'Cool Skill', 'description' => 'Does cool things'], $result);
	}

	public function testParseMetadataFieldsInvalidYaml(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/Invalid YAML frontmatter/');
		$this->service->parseMetadataFields("name: [unclosed bracket\n", '/some/path/to/SKILL.md');
	}

	public function testParseMetadataFieldsMissingName(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/missing required metadata field "name"/');
		$this->service->parseMetadataFields("description: Only a description\n", '/some/path/to/SKILL.md');
	}

	public function testParseMetadataFieldsMissingDescription(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/missing required metadata field "description"/');
		$this->service->parseMetadataFields("name: My Skill\n", '/some/path/to/SKILL.md');
	}

	public function testParseMetadataFieldsEmptyName(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/missing required metadata field "name"/');
		$this->service->parseMetadataFields("name: ''\ndescription: Something\n", '/some/path/to/SKILL.md');
	}

	public function testParseMetadataFieldsNotAMapping(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/not a YAML mapping/');
		$this->service->parseMetadataFields("just a string\n", '/some/path/to/SKILL.md');
	}

	// -------------------------------------------------------------------------
	// storeSkill
	// -------------------------------------------------------------------------

	public function testStoreSkillCreatesSkillAndReturnsCreated(): void {
		$result = $this->service->storeSkill(self::TEST_USER, 'my-skill', 'Does something', '## Instructions');

		$this->assertSame('created', $result);

		$userFolder = $this->rootFolder->getUserFolder(self::TEST_USER);
		$this->assertTrue($userFolder->nodeExists($this->getSkillsPath(self::TEST_USER) . '/my-skill/SKILL.md'));
	}

	public function testStoreSkillOverwritesExistingSkill(): void {
		$this->service->storeSkill(self::TEST_USER, 'my-skill', 'First', 'First body');
		$result = $this->service->storeSkill(self::TEST_USER, 'my-skill', 'Second', 'Second body');

		$this->assertSame('overwritten', $result);
	}

	public function testStoreSkillRejectsEmptyName(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->storeSkill(self::TEST_USER, '', 'desc', 'body');
	}

	public function testStoreSkillRejectsNameWithSlash(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->storeSkill(self::TEST_USER, 'foo/bar', 'desc', 'body');
	}

	public function testStoreSkillRejectsEmptyDescription(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->storeSkill(self::TEST_USER, 'my-skill', '', 'body');
	}

	// -------------------------------------------------------------------------
	// listSkills
	// -------------------------------------------------------------------------

	public function testListSkillsReturnsStoredSkill(): void {
		$this->service->storeSkill(self::TEST_USER, 'skill-a', 'Skill A description', '## Content');

		$skills = $this->service->listSkills(self::TEST_USER);

		$this->assertCount(1, $skills);
		$this->assertSame('skill-a', $skills[0]['name']);
		$this->assertSame('Skill A description', $skills[0]['description']);
	}

	public function testListSkillsSkipsFolderWithoutSkillMd(): void {
		$this->service->storeSkill(self::TEST_USER, 'good-skill', 'Valid', '## Content');

		// create a skill folder without SKILL.md
		$userFolder = $this->rootFolder->getUserFolder(self::TEST_USER);
		/** @var Folder $skillsFolder */
		$skillsFolder = $userFolder->get($this->getSkillsPath(self::TEST_USER));
		$skillsFolder->newFolder('no-md-skill');

		$skills = $this->service->listSkills(self::TEST_USER);

		$this->assertCount(1, $skills);
		$this->assertSame('good-skill', $skills[0]['name']);
	}

	public function testListSkillsSkipsMalformedFrontmatter(): void {
		$this->service->storeSkill(self::TEST_USER, 'good-skill', 'Valid', '## Content');
		$this->writeRawSkillFile(self::TEST_USER, 'broken-skill', "no frontmatter at all\n");

		$skills = $this->service->listSkills(self::TEST_USER);

		$this->assertCount(1, $skills);
		$this->assertSame('good-skill', $skills[0]['name']);
	}

	public function testListSkillsReturnsEmptyWhenNoSkillsExist(): void {
		$skills = $this->service->listSkills(self::TEST_USER);

		$this->assertSame([], $skills);
	}

	// -------------------------------------------------------------------------
	// loadSkill
	// -------------------------------------------------------------------------

	public function testLoadSkillReturnsFullContent(): void {
		$this->service->storeSkill(self::TEST_USER, 'my-skill', 'Does something', '## Instructions here');

		$content = $this->service->loadSkill(self::TEST_USER, 'my-skill');

		$this->assertStringContainsString('name: my-skill', $content);
		$this->assertStringContainsString('## Instructions here', $content);
	}

	public function testLoadSkillReturnsMultilineContent(): void {
		$body = "## Step 1\n\nDo the first thing.\n\n## Step 2\n\nDo the second thing.\n\n- item one\n- item two\n";
		$this->service->storeSkill(self::TEST_USER, 'my-skill', 'Multi-step skill', $body);

		$content = $this->service->loadSkill(self::TEST_USER, 'my-skill');

		$this->assertStringContainsString('## Step 1', $content);
		$this->assertStringContainsString('## Step 2', $content);
		$this->assertStringContainsString('- item one', $content);
		$this->assertStringContainsString('- item two', $content);
	}

	public function testLoadSkillThrowsForMissingSkill(): void {
		$this->expectException(\OCP\Files\NotFoundException::class);
		$this->service->loadSkill(self::TEST_USER, 'non-existent-skill');
	}

	// -------------------------------------------------------------------------
	// Global skills folder
	// -------------------------------------------------------------------------

	public function testGetGlobalSkillsFolderReturnsNullWhenNotConfigured(): void {
		$this->assertNull($this->service->getGlobalSkillsFolder());
	}

	public function testGetGlobalSkillsFolderReturnsConfiguredFolder(): void {
		$this->storeGlobalSkill('admin-skill', 'Admin desc', '## Admin');
		$this->service->setGlobalSkillsFolder(self::ADMIN_USER, self::GLOBAL_POOL_FOLDER);

		$folder = $this->service->getGlobalSkillsFolder();
		$this->assertInstanceOf(Folder::class, $folder);
	}

	public function testGetGlobalSkillsFolderReturnsNullAfterFolderDeleted(): void {
		$adminUserFolder = $this->rootFolder->getUserFolder(self::ADMIN_USER);
		$adminUserFolder->newFolder('TempGlobal');
		$this->service->setGlobalSkillsFolder(self::ADMIN_USER, 'TempGlobal');

		$adminUserFolder->get('TempGlobal')->delete();

		$this->assertNull($this->service->getGlobalSkillsFolder());
	}

	// -------------------------------------------------------------------------
	// Global + user skill merging
	// -------------------------------------------------------------------------

	public function testListSkillsIncludesGlobalSkills(): void {
		$this->storeGlobalSkill('global-skill', 'From admin', '## Admin content');
		$this->service->setGlobalSkillsFolder(self::ADMIN_USER, self::GLOBAL_POOL_FOLDER);

		$skills = $this->service->listSkills(self::TEST_USER);

		$names = array_column($skills, 'name');
		$this->assertContains('global-skill', $names);
	}

	public function testUserSkillOverridesGlobalSkillOnNameCollision(): void {
		$this->storeGlobalSkill('popular-skill', 'Global version', '## Global');
		$this->storeGlobalSkill('global-only', 'Only global', '## Global only');
		$this->service->setGlobalSkillsFolder(self::ADMIN_USER, self::GLOBAL_POOL_FOLDER);

		$this->service->storeSkill(self::TEST_USER, 'popular-skill', 'User version', '## User');

		$skills = $this->service->listSkills(self::TEST_USER);
		$byName = array_column($skills, null, 'name');

		$this->assertSame('User version', $byName['popular-skill']['description']);
		$this->assertArrayHasKey('global-only', $byName);
	}

	public function testLoadSkillPrefersUserSkillOverGlobalOnNameCollision(): void {
		$this->storeGlobalSkill('popular-skill', 'Global version', '## Global content');
		$this->service->setGlobalSkillsFolder(self::ADMIN_USER, self::GLOBAL_POOL_FOLDER);

		$this->service->storeSkill(self::TEST_USER, 'popular-skill', 'User version', '## User content');

		$content = $this->service->loadSkill(self::TEST_USER, 'popular-skill');

		$this->assertStringContainsString('## User content', $content);
		$this->assertStringNotContainsString('## Global content', $content);
	}

	public function testLoadSkillFallsBackToGlobalWhenNotInUserSkills(): void {
		$this->storeGlobalSkill('global-only-skill', 'From admin', '## Only in global');
		$this->service->setGlobalSkillsFolder(self::ADMIN_USER, self::GLOBAL_POOL_FOLDER);

		$content = $this->service->loadSkill(self::TEST_USER, 'global-only-skill');

		$this->assertStringContainsString('## Only in global', $content);
	}
}
