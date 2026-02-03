<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Dav;

use OCP\Calendar\Exceptions\CalendarException;
use OCP\Calendar\ICalendar;
use OCP\Calendar\ICreateFromString;
use OCP\IDBConnection;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class ScheduledTasksCalendar implements ICalendar, ICreateFromString {
	public const CALENDAR_URI = 'assistant-scheduled-tasks';

	public function __construct(
		private IDBConnection $db,
		private string $principalUri,
	) {
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return self::CALENDAR_URI;
	}

	/**
	 * @return string
	 */
	public function getUri(): string {
		return self::CALENDAR_URI;
	}

	/**
	 * @return string|null
	 */
	public function getDisplayName(): ?string {
		return 'Assistant Scheduled Tasks';
	}

	/**
	 * @return string|null
	 */
	public function getDisplayColor(): ?string {
		return '#0082c9';
	}

	/**
	 * @param string $pattern
	 * @param array $searchProperties
	 * @param array $options
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 */
	public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null): array {
		$userId = $this->getUserIdFromPrincipal($this->principalUri);
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'uri', 'calendardata', 'lastmodified', 'etag')
			->from('assistant_scheduled_tasks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)));

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		$result = $qb->executeQuery();
		$events = [];

		while ($row = $result->fetch()) {
			try {
				$vcalendar = Reader::read($row['calendardata']);
				if (!$vcalendar->VEVENT) {
					continue;
				}

				// If pattern search is requested, check if the event matches
				if ($pattern !== '' && !empty($searchProperties)) {
					$matches = false;
					foreach ($searchProperties as $property) {
						$propertyValue = (string)($vcalendar->VEVENT->{$property} ?? '');
						if (stripos($propertyValue, $pattern) !== false) {
							$matches = true;
							break;
						}
					}
					if (!$matches) {
						continue;
					}
				}

				// Handle time range filter
				if (isset($options['timerange'])) {
					$dtstart = $vcalendar->VEVENT->DTSTART;
					if ($dtstart) {
						$eventStart = $dtstart->getDateTime();
						if (isset($options['timerange']['start']) && $eventStart < $options['timerange']['start']) {
							continue;
						}
						if (isset($options['timerange']['end']) && $eventStart > $options['timerange']['end']) {
							continue;
						}
					}
				}

				// Handle UID filter
				if (isset($options['uid'])) {
					$uid = (string)($vcalendar->VEVENT->UID ?? '');
					if ($uid !== $options['uid']) {
						continue;
					}
				}

				// Handle type filter
				if (isset($options['types']) && !in_array('VEVENT', $options['types'], true)) {
					continue;
				}

				$events[] = [
					'id' => $row['id'],
					'uri' => $row['uri'],
					'type' => 'VEVENT',
					'uid' => (string)($vcalendar->VEVENT->UID ?? ''),
					'objects' => [$vcalendar],
					'calendardata' => $row['calendardata'],
				];
			} catch (\Exception $e) {
				// Skip invalid calendar objects
				continue;
			}
		}
		$result->closeCursor();

		return $events;
	}

	/**
	 * @return int
	 */
	public function getPermissions(): int {
		return \OCP\Constants::PERMISSION_READ
			| \OCP\Constants::PERMISSION_CREATE
			| \OCP\Constants::PERMISSION_UPDATE
			| \OCP\Constants::PERMISSION_DELETE;
	}

	/**
	 * @return bool
	 */
	public function isDeleted(): bool {
		return false;
	}

	/**
	 * Create an event from an ICS string
	 *
	 * @param string $name
	 * @param string $calendarData
	 * @throws CalendarException
	 */
	public function createFromString(string $name, string $calendarData): void {
		$this->createFromStringMinimal($name, $calendarData);
	}

	/**
	 * Create an event from an ICS string (minimal implementation)
	 *
	 * @param string $name
	 * @param string $calendarData
	 * @throws CalendarException
	 */
	public function createFromStringMinimal(string $name, string $calendarData): void {
		$userId = $this->getUserIdFromPrincipal($this->principalUri);

		try {
			// Validate the calendar data
			$vcalendar = Reader::read($calendarData);
			if (!$vcalendar->VEVENT) {
				throw new CalendarException('Invalid calendar data: no VEVENT found');
			}

			// Ensure .ics extension
			if (!str_ends_with($name, '.ics')) {
				throw new CalendarException('File name must end with .ics');
			}

			$etag = md5($calendarData);

			// Check if event already exists
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('assistant_scheduled_tasks')
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)))
				->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($name)));

			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				// Update existing event
				$qb = $this->db->getQueryBuilder();
				$qb->update('assistant_scheduled_tasks')
					->set('calendardata', $qb->createNamedParameter($calendarData))
					->set('lastmodified', $qb->createNamedParameter(time()))
					->set('etag', $qb->createNamedParameter($etag))
					->set('is_processed', $qb->createNamedParameter(0))
					->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
					->andWhere($qb->expr()->eq('calendar_id', $qb->createNamedParameter(self::CALENDAR_URI)))
					->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($name)))
					->executeStatement();
			} else {
				// Insert new event
				$qb = $this->db->getQueryBuilder();
				$qb->insert('assistant_scheduled_tasks')
					->values([
						'calendar_id' => $qb->createNamedParameter(self::CALENDAR_URI),
						'uri' => $qb->createNamedParameter($name),
						'calendardata' => $qb->createNamedParameter($calendarData),
						'lastmodified' => $qb->createNamedParameter(time()),
						'etag' => $qb->createNamedParameter($etag),
						'user_id' => $qb->createNamedParameter($userId),
						'is_processed' => $qb->createNamedParameter(0),
					])
					->executeStatement();
			}
		} catch (\Exception $e) {
			throw new CalendarException('Failed to create calendar event: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Get the user ID from the principal URI
	 *
	 * @param string $principalUri
	 * @return string
	 */
	private function getUserIdFromPrincipal(string $principalUri): string {
		// Principal URI format: principals/users/username
		$parts = explode('/', $principalUri);
		return end($parts);
	}
}
