<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Dav;

use Sabre\CalDAV\ICalendarObject;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAVACL\IACL;

class ScheduledTasksCalendarObject implements ICalendarObject, IACL {
	public function __construct(
		private ScheduledTasksBackend $backend,
		private array $objectData,
		private string $principalUri,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function put($data) {
		return $this->backend->updateCalendarObject($this->objectData['uri'], $data);
	}

	/**
	 * @inheritDoc
	 */
	public function get() {
		return $this->objectData['calendardata'];
	}

	/**
	 * @inheritDoc
	 */
	public function getContentType() {
		return 'text/calendar; charset=utf-8; component=VEVENT';
	}

	/**
	 * @inheritDoc
	 */
	public function getETag() {
		return '"' . $this->objectData['etag'] . '"';
	}

	/**
	 * @inheritDoc
	 */
	public function getSize() {
		return strlen($this->objectData['calendardata']);
	}

	/**
	 * @inheritDoc
	 */
	public function delete() {
		$this->backend->deleteCalendarObject($this->objectData['uri']);
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->objectData['uri'];
	}

	/**
	 * @inheritDoc
	 */
	public function setName($name) {
		throw new Forbidden('Renaming calendar objects is not allowed');
	}

	/**
	 * @inheritDoc
	 */
	public function getLastModified() {
		return $this->objectData['lastmodified'];
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
}
