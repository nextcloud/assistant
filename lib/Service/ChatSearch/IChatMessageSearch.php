<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service\ChatSearch;

use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Service\InternalException;

/**
 * Backend used to search the chat messages, bound in Application::register
 */
interface IChatMessageSearch {
	/**
	 * Find the messages of a user's sessions matching the query, most relevant first
	 *
	 * @param string $userId
	 * @param string $query Non-empty text to search for, matched literally and case-insensitively
	 * @param bool $isAssignment Whether to search in the sessions for assignments (true) or not (false)
	 * @param int $limit
	 * @return list<Message>
	 * @throws InternalException
	 */
	public function search(string $userId, string $query, bool $isAssignment, int $limit): array;
}
