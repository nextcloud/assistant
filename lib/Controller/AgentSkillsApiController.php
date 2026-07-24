<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OC\User\NoUserException;
use OCA\Assistant\Service\AgentSkillsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

class AgentSkillsApiController extends OCSController {

	private const MAX_SKILL_NAME_LENGTH = 64;
	private const MAX_DESCRIPTION_LENGTH = 1024;
	private const MAX_CONTENT_LENGTH = 50_000;

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $l10n,
		private AgentSkillsService $agentSkillsService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List available skills for the current user
	 *
	 * Returns each skill's name and description, as parsed from the YAML frontmatter
	 * of its SKILL.md file.
	 * Includes admin-configured global skills when configured.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{skills: list<array{name: string, description: string}>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: Skills listed successfully
	 * 401: User is not authenticated
	 * 404: Skills folder not found
	 * 500: Skills could not be listed
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['agent_skills'])]
	public function listSkills(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$skills = $this->agentSkillsService->listSkills($this->userId);
			return new DataResponse(['skills' => $skills]);
		} catch (NoUserException $e) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		} catch (NotFoundException|NotPermittedException $e) {
			$this->logger->debug('Skills folder not found', ['exception' => $e]);
			return new DataResponse(['error' => $this->l10n->t('Skills folder not found')], Http::STATUS_NOT_FOUND);
		} catch (\Exception|Throwable $e) {
			$this->logger->error('Failed to list skills', ['exception' => $e]);
			return new DataResponse(['error' => $this->l10n->t('Failed to list skills')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Load a skill's full content
	 *
	 * Returns the full content of the SKILL.md file (frontmatter + body) for the given skill.
	 * If the skill is not present in the user's skills, the admin-configured global skills folder
	 * is used as a fallback when configured.
	 *
	 * @param string $skillName The skill's folder name
	 * @return DataResponse<Http::STATUS_OK, array{content: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: Skill content returned
	 * 400: Skill name is too long
	 * 401: User is not authenticated
	 * 404: Skill not found
	 * 500: Skill could not be loaded
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['agent_skills'])]
	public function loadSkill(string $skillName): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		if (mb_strlen($skillName) > self::MAX_SKILL_NAME_LENGTH) {
			return new DataResponse(['error' => $this->l10n->t('Skill name is too long (max %d characters)', [self::MAX_SKILL_NAME_LENGTH])], Http::STATUS_BAD_REQUEST);
		}
		try {
			$content = $this->agentSkillsService->loadSkill($this->userId, $skillName);
			return new DataResponse(['content' => $content]);
		} catch (NoUserException $e) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		} catch (NotFoundException|NotPermittedException $e) {
			$this->logger->debug('Skill not found', ['exception' => $e]);
			return new DataResponse(['error' => $this->l10n->t('Skill not found')], Http::STATUS_NOT_FOUND);
		} catch (\Exception|Throwable $e) {
			$this->logger->error('Failed to load the skill', ['exception' => $e]);
			return new DataResponse(['error' => $this->l10n->t('Failed to load the skill')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Store a skill
	 *
	 * Create or overwrite a skill in the user's storage. The `action` field of the response
	 * is 'created' if a new SKILL.md was written, or 'overwritten' if an existing one was replaced.
	 *
	 * @param string $skillName The skill's folder name (also used as the frontmatter `name`)
	 * @param string $description Short, agent-facing description of when to use the skill
	 * @param string $content Markdown body to write after the frontmatter
	 * @return DataResponse<Http::STATUS_OK, array{action: string}, array{}>|DataResponse<Http::STATUS_CREATED, array{action: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Existing skill overwritten
	 * 201: New skill created
	 * 400: Invalid skill name
	 * 401: User is not authenticated
	 * 500: Skill could not be stored
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['agent_skills'])]
	public function storeSkill(string $skillName, string $description, string $content): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		}
		if (mb_strlen($skillName) > self::MAX_SKILL_NAME_LENGTH) {
			return new DataResponse(['error' => $this->l10n->t('Skill name is too long (max %d characters)', [self::MAX_SKILL_NAME_LENGTH])], Http::STATUS_BAD_REQUEST);
		}
		if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
			return new DataResponse(['error' => $this->l10n->t('Description is too long (max %d characters)', [self::MAX_DESCRIPTION_LENGTH])], Http::STATUS_BAD_REQUEST);
		}
		if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
			return new DataResponse(['error' => $this->l10n->t('Content is too long (max %d characters)', [self::MAX_CONTENT_LENGTH])], Http::STATUS_BAD_REQUEST);
		}
		try {
			$action = $this->agentSkillsService->storeSkill($this->userId, $skillName, $description, $content);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (NoUserException $e) {
			return new DataResponse(['error' => $this->l10n->t('Unknown user')], Http::STATUS_UNAUTHORIZED);
		} catch (\Exception|Throwable $e) {
			$this->logger->error('Failed to store the skill', ['exception' => $e]);
			return new DataResponse(['error' => $this->l10n->t('Failed to store the skill')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$status = $action === 'created' ? Http::STATUS_CREATED : Http::STATUS_OK;
		return new DataResponse(['action' => $action], $status);
	}
}
