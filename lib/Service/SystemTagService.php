<?php


declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;

class SystemTagService {

	const AI_TAG_NAME = 'Generated using AI';

	public function __construct(
		private ISystemTagManager $systemTagManager,
		private ISystemTagObjectMapper $systemTagObjectMapper,
	) {
	}

	public function getAiTag() {
		try {
			return $this->systemTagManager->getTag(self::AI_TAG_NAME, true, true);
		} catch (TagNotFoundException $e) {
			return $this->systemTagManager->createTag(self::AI_TAG_NAME, true, true);
		}
	}

	/**
	 * @param string|list<string> $fileId
	 * @return void
	 * @throws TagNotFoundException
	 */
	public function assignAiTagToFile(string|array $fileId) {
		$this->systemTagObjectMapper->assignTags($fileId, 'files', $this->getAiTag()->getId());
	}
}