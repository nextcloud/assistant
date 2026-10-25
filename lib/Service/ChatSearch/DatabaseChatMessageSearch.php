<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service\ChatSearch;

use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Service\InternalException;
use OCP\DB\Exception;

/**
 * Searches the messages directly in the database, most recent first
 */
class DatabaseChatMessageSearch implements IChatMessageSearch {
	public function __construct(
		private readonly MessageMapper $messageMapper,
	) {
	}

	public function search(string $userId, string $query, bool $isAssignment, int $limit): array {
		try {
			return $this->messageMapper->searchUserMessages($userId, $query, $isAssignment, $limit);
		} catch (Exception $e) {
			throw new InternalException(previous: $e);
		}
	}
}
