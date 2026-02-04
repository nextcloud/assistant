<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Dav;

use OCP\IDBConnection;
use Sabre\VObject\Reader;

class ScheduledTasksBackend {
	public const CALENDAR_URI = 'assistant-scheduled-tasks';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Get all calendar objects for a user
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getCalendarObjects(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'uri', 'calendardata', 'lastmodified', 'etag')
			->from('assistant_scheduled_tasks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)));

		$result = $qb->executeQuery();
		$objects = [];

		while ($row = $result->fetch()) {
			$objects[] = [
				'id' => $row['id'],
				'uri' => $row['uri'],
				'calendardata' => $row['calendardata'],
				'lastmodified' => (int)$row['lastmodified'],
				'etag' => $row['etag'],
			];
		}
		$result->closeCursor();

		return $objects;
	}

	/**
	 * Get a specific calendar object
	 *
	 * @param string $userId
	 * @param string $objectUri
	 * @return array|null
	 */
	public function getCalendarObject(string $userId, string $objectUri): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'uri', 'calendardata', 'lastmodified', 'etag')
			->from('assistant_scheduled_tasks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)))
			->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($objectUri)));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		return [
			'id' => $row['id'],
			'uri' => $row['uri'],
			'calendardata' => $row['calendardata'],
			'lastmodified' => (int)$row['lastmodified'],
			'etag' => $row['etag'],
		];
	}

	/**
	 * Create a new calendar object
	 *
	 * @param string $userId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @return string The ETag
	 */
	public function createCalendarObject(string $userId, string $objectUri, string $calendarData): string {
		// Validate the calendar data
		try {
			$vcalendar = Reader::read($calendarData);
			if (!$vcalendar->VEVENT) {
				throw new \InvalidArgumentException('Invalid calendar data: no VEVENT found');
			}
		} catch (\Exception $e) {
			throw new \InvalidArgumentException('Invalid calendar data: ' . $e->getMessage(), 0, $e);
		}

		// Ensure .ics extension
		if (!str_ends_with($objectUri, '.ics')) {
			throw new \InvalidArgumentException('Object URI must end with .ics');
		}

		$etag = md5($calendarData);

		$qb = $this->db->getQueryBuilder();
		$qb->insert('assistant_scheduled_tasks')
			->values([
				'calendar_id' => $qb->createNamedParameter(self::CALENDAR_URI),
				'uri' => $qb->createNamedParameter($objectUri),
				'calendardata' => $qb->createNamedParameter($calendarData),
				'lastmodified' => $qb->createNamedParameter(time()),
				'etag' => $qb->createNamedParameter($etag),
				'user_id' => $qb->createNamedParameter($userId),
				'is_processed' => $qb->createNamedParameter(0),
			])
			->executeStatement();

		return $etag;
	}

	/**
	 * Update an existing calendar object
	 *
	 * @param string $objectUri
	 * @param string $calendarData
	 * @return string The new ETag
	 */
	public function updateCalendarObject(string $objectUri, string $calendarData): string {
		// Validate the calendar data
		try {
			$vcalendar = Reader::read($calendarData);
			if (!$vcalendar->VEVENT) {
				throw new \InvalidArgumentException('Invalid calendar data: no VEVENT found');
			}
		} catch (\Exception $e) {
			throw new \InvalidArgumentException('Invalid calendar data: ' . $e->getMessage(), 0, $e);
		}

		$etag = md5($calendarData);

		$qb = $this->db->getQueryBuilder();
		$qb->update('assistant_scheduled_tasks')
			->set('calendardata', $qb->createNamedParameter($calendarData))
			->set('lastmodified', $qb->createNamedParameter(time()))
			->set('etag', $qb->createNamedParameter($etag))
			->set('is_processed', $qb->createNamedParameter(0))
			->where($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)))
			->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($objectUri)))
			->executeStatement();

		return $etag;
	}

	/**
	 * Delete a calendar object
	 *
	 * @param string $objectUri
	 */
	public function deleteCalendarObject(string $objectUri): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('assistant_scheduled_tasks')
			->where($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)))
			->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($objectUri)))
			->executeStatement();
	}

	/**
	 * Query calendar objects with filters (optimized for calendarQuery)
	 *
	 * @param string $userId
	 * @param array $filters CalDAV query filters
	 * @return array Array of URIs matching the filters
	 */
	public function calendarQuery(string $userId, array $filters): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('uri', 'calendardata')
			->from('assistant_scheduled_tasks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)));

		// Extract time-range from filters if present
		$timeRange = $this->extractTimeRangeFromFilters($filters);
		if ($timeRange !== null) {
			// For time-range queries, we still need to parse calendar data
			// but we can optimize by filtering in-memory with minimal data
			$qb->select('uri', 'calendardata');
		}

		$result = $qb->executeQuery();
		$matchingUris = [];

		while ($row = $result->fetch()) {
			// For simple queries without complex filters, we could return all URIs
			// For complex queries with time-range or property filters, parse and check
			if ($this->shouldIncludeInResults($row['calendardata'], $filters)) {
				$matchingUris[] = $row['uri'];
			}
		}
		$result->closeCursor();

		return $matchingUris;
	}

	/**
	 * Extract time-range filter from CalDAV filters
	 *
	 * @param array $filters
	 * @return array|null
	 */
	private function extractTimeRangeFromFilters(array $filters): ?array {
		if (!isset($filters['comp-filters'])) {
			return null;
		}

		foreach ($filters['comp-filters'] as $compFilter) {
			if (isset($compFilter['comp-filters'])) {
				foreach ($compFilter['comp-filters'] as $subCompFilter) {
					if ($subCompFilter['name'] === 'VEVENT' && isset($subCompFilter['time-range'])) {
						return $subCompFilter['time-range'];
					}
				}
			}
		}

		return null;
	}

	/**
	 * Check if calendar data should be included in results based on filters
	 *
	 * @param string $calendarData
	 * @param array $filters
	 * @return bool
	 */
	private function shouldIncludeInResults(string $calendarData, array $filters): bool {
		// If no filters, include everything
		if (empty($filters['comp-filters'])) {
			return true;
		}

		try {
			$vcalendar = Reader::read($calendarData);
		} catch (\Exception $e) {
			return false;
		}

		// Check component type filter
		if (!isset($vcalendar->VEVENT)) {
			return false;
		}

		// Check time-range filter
		$timeRange = $this->extractTimeRangeFromFilters($filters);
		if ($timeRange !== null) {
			$vevent = $vcalendar->VEVENT;
			if (isset($vevent->DTSTART)) {
				$dtstart = $vevent->DTSTART->getDateTime();

				if (isset($timeRange['start'])) {
					$start = new \DateTime($timeRange['start']);
					if ($dtstart < $start) {
						return false;
					}
				}

				if (isset($timeRange['end'])) {
					$end = new \DateTime($timeRange['end']);
					if ($dtstart > $end) {
						return false;
					}
				}
			}
		}

		return true;
	}
}
