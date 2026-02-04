<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Dav;

use Sabre\CalDAV\ICalendar;
use Sabre\CalDAV\Plugin as CalDAVPlugin;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

class ScheduledTasksSabreCalendar implements ICalendar {
	public function __construct(
		private ScheduledTasksBackend $backend,
		private string $principalUri,
		private string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function createFile($name, $data = null) {
		if (!is_string($data)) {
			throw new \InvalidArgumentException('Calendar data must be a string');
		}
		$this->backend->createCalendarObject($this->userId, $name, $data);
	}

	/**
	 * @inheritDoc
	 */
	public function createDirectory($name) {
		throw new Forbidden('Creating directories is not allowed');
	}

	/**
	 * @inheritDoc
	 */
	public function getChild($name) {
		$objectData = $this->backend->getCalendarObject($this->userId, $name);
		if (!$objectData) {
			throw new NotFound('Calendar object not found');
		}
		return new ScheduledTasksCalendarObject($this->backend, $objectData, $this->principalUri);
	}

	/**
	 * @inheritDoc
	 */
	public function getChildren() {
		$objects = $this->backend->getCalendarObjects($this->userId);
		$children = [];
		foreach ($objects as $objectData) {
			$children[] = new ScheduledTasksCalendarObject($this->backend, $objectData, $this->principalUri);
		}
		return $children;
	}

	/**
	 * @inheritDoc
	 */
	public function childExists($name) {
		$objectData = $this->backend->getCalendarObject($this->userId, $name);
		return $objectData !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function delete() {
		throw new Forbidden('Deleting the calendar is not allowed');
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return ScheduledTasksBackend::CALENDAR_URI;
	}

	/**
	 * @inheritDoc
	 */
	public function setName($name) {
		throw new Forbidden('Renaming the calendar is not allowed');
	}

	/**
	 * @inheritDoc
	 */
	public function getLastModified() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function propPatch(PropPatch $propPatch) {
		// Allow setting CalDAV properties but ignore them
		$propPatch->handle([
			'{DAV:}displayname',
			'{' . CalDAVPlugin::NS_CALDAV . '}calendar-description',
			'{' . CalDAVPlugin::NS_CALDAV . '}calendar-timezone',
			'{http://apple.com/ns/ical/}calendar-order',
			'{http://apple.com/ns/ical/}calendar-color',
		], function ($properties) {
			// Ignore property updates
			return true;
		});
	}

	/**
	 * @inheritDoc
	 */
	public function getProperties($properties) {
		return [
			'{DAV:}displayname' => 'Assistant Scheduled Tasks',
			'{' . CalDAVPlugin::NS_CALDAV . '}calendar-description' => 'Calendar for scheduled assistant tasks',
			'{' . CalDAVPlugin::NS_CALDAV . '}supported-calendar-component-set' =>
				new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet(['VEVENT']),
			'{http://apple.com/ns/ical/}calendar-color' => '#0082c9',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getOwner() {
		return $this->principalUri;
	}

	/**
	 * @inheritDoc
	 */
	public function getGroup() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getACL() {
		return [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->principalUri,
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}write',
				'principal' => $this->principalUri,
				'protected' => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function setACL(array $acl) {
		throw new Forbidden('Setting ACL is not allowed');
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedPrivilegeSet() {
		return null;
	}

	/**
	 * Performs a calendar-query on the contents of this calendar.
	 *
	 * The calendar-query is defined in RFC4791 : CalDAV. Using the
	 * calendar-query it is possible for a client to request a specific set of
	 * object, based on contents of iCalendar properties, date-ranges and
	 * iCalendar component types (VTODO, VEVENT).
	 *
	 * This method should just return a list of (relative) urls that match this
	 * query.
	 *
	 * The list of filters are specified as an array. The exact array is
	 * documented by \Sabre\CalDAV\CalendarQueryParser.
	 *
	 * @param array $filters
	 * @return array
	 */
	public function calendarQuery(array $filters) {
		// Delegate to backend for optimized query execution
		return $this->backend->calendarQuery($this->userId, $filters);
	}
}
