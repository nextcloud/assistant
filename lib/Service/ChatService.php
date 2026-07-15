<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\DB\Exception;

class ChatService {
	public const EMPTY_CONVERSATION_TOKEN = '{}';

	public function __construct(
		private readonly SessionMapper $sessionMapper,
		private readonly MessageMapper $messageMapper,
	) {
	}

    /**
	 * @throws InternalException
	 */
	public function deleteAllUserChatData(string $userId): void {
		try {
			$sessions = $this->sessionMapper->getUserSessions($userId);
			foreach ($sessions as $session) {
				$this->messageMapper->deleteMessagesBySession($session->getId());
			}
			$this->sessionMapper->deleteAllSessionsForUser($userId);
		} catch (Exception|\RuntimeException $e) {
			throw new InternalException(previous: $e);
		}
	}

}