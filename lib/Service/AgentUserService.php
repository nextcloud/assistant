<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCP\IUser;
use OCP\IUserManager;

/**
 * Provisioning of the Nextcloud user account backing an agent
 */
class AgentUserService {

	private const PASSWORD_LENGTH = 40;

	public function __construct(
		private IUserManager $userManager,
	) {
	}

	/**
	 * Create the NC user used by an agent, if it does not exist yet.
	 * The user gets a random password that is never shown or stored,
	 * so the account cannot be used to log in.
	 *
	 * @return IUser
	 * @throws AgentUserException if the user id is not available or creation failed
	 */
	public function createAgentUser(string $name, string $userId): IUser {
		$userId = trim(mb_strtolower($userId));
		if (!preg_match('/^[a-z0-9_\.\-@]+$/', $userId)) {
			throw new AgentUserException('Invalid agent user id. Allowed characters: lowercase letters, digits, dot, dash, underscore and @');
		}
		if ($this->userManager->userExists($userId)) {
			throw new AgentUserException('The user id "' . $userId . '" already exists. Please choose another one.');
		}

		$password = bin2hex(random_bytes(self::PASSWORD_LENGTH));
		try {
			$user = $this->userManager->createUser($userId, $password);
		} catch (\Exception $e) {
			throw new AgentUserException('Failed to create the agent user "' . $userId . '": ' . $e->getMessage(), previous: $e);
		}
		$user->setDisplayName($name);
		return $user;
	}
}
