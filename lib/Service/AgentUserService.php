<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Provisioning of the Nextcloud user account backing an agent
 */
class AgentUserService {

	private const PASSWORD_LENGTH = 40;

	public function __construct(
		private IUserManager $userManager,
		private IL10N $l10n,
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
			throw new AgentUserException($this->l10n->t('Invalid agent user id. Allowed characters: lowercase letters, digits, dot, dash, underscore and @'));
		}
		if ($this->userManager->userExists($userId)) {
			throw new AgentUserException($this->l10n->t('The user id "%s" already exists. Please choose another one.', [$userId]));
		}

		$password = bin2hex(random_bytes(self::PASSWORD_LENGTH));
		try {
			$user = $this->userManager->createUser($userId, $password);
		} catch (\Exception $e) {
			throw new AgentUserException($this->l10n->t('Failed to create the agent user "%s"', [$userId]) . ': ' . $e->getMessage(), previous: $e);
		}
		$user->setDisplayName($name);
		return $user;
	}

	/**
	 * Delete the NC user backing an agent, when it exists.
	 * Used when an agent creation failed after its user account was created,
	 * to not leave an orphan account behind.
	 */
	public function deleteAgentUser(string $userId): void {
		$user = $this->userManager->get(trim(mb_strtolower($userId)));
		if ($user !== null) {
			$user->delete();
		}
	}
}
