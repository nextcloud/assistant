<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Dav;

use OCA\Assistant\AppInfo\Application;
use OCP\IDBConnection;

/**
 * Calendar provider for integrating scheduled tasks calendar into CalDAV
 *
 * This class implements the integration interface to expose the scheduled tasks
 * calendar through Nextcloud's CalDAV server using Sabre DAV interfaces.
 */
class ScheduledTasksCalendarIntegration implements \OCA\DAV\CalDAV\Integration\ICalendarProvider {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getAppId(): string {
		return Application::APP_ID;
	}

	/**
	 * @inheritDoc
	 */
	public function fetchAllForCalendarHome(string $principalUri): array {
		$userId = $this->extractUserId($principalUri);
		if ($userId === null) {
			return [];
		}

		$backend = new ScheduledTasksBackend($this->db);
		return [
			new ScheduledTasksSabreCalendar($backend, $principalUri, $userId),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {
		return $calendarUri === ScheduledTasksBackend::CALENDAR_URI;
	}

	/**
	 * @inheritDoc
	 */
	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?\Sabre\CalDAV\ICalendar {
		if ($calendarUri !== ScheduledTasksBackend::CALENDAR_URI) {
			return null;
		}

		$userId = $this->extractUserId($principalUri);
		if ($userId === null) {
			return null;
		}

		$backend = new ScheduledTasksBackend($this->db);
		return new ScheduledTasksSabreCalendar($backend, $principalUri, $userId);
	}

	/**
	 * Extract user ID from principal URI
	 *
	 * @param string $principalUri Format: principals/users/{userId}
	 * @return string|null
	 */
	private function extractUserId(string $principalUri): ?string {
		$parts = explode('/', $principalUri);
		if (count($parts) >= 3 && $parts[0] === 'principals' && $parts[1] === 'users') {
			return $parts[2];
		}
		return null;
	}
}
