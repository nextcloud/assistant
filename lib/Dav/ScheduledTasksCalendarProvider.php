<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Dav;

use OCP\Calendar\ICalendar;
use OCP\Calendar\ICalendarProvider;
use OCP\IDBConnection;

class ScheduledTasksCalendarProvider implements ICalendarProvider {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Return a list of calendars for a principal URI
	 *
	 * @param string $principalUri
	 * @param array $calendarUris
	 * @return ICalendar[]
	 */
	public function getCalendars(string $principalUri, array $calendarUris = []): array {
		// Only return calendar if no filter is specified or our calendar is in the filter
		if (!empty($calendarUris) && !in_array(ScheduledTasksCalendar::CALENDAR_URI, $calendarUris, true)) {
			return [];
		}

		$calendar = new ScheduledTasksCalendar(
			$this->db,
			$principalUri
		);

		return [$calendar];
	}
}
