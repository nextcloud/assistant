<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests;

use OCA\Assistant\Db\Assignment;

class AssignmentTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider isDueDataProvider
	 */
	public function testIsDue(string $rrule, string $startsAt, string $lastRunAt, string $timezone, string $now, bool $expected) {
		$assignment = new Assignment();
		$assignment->setRecurrence($rrule);
		$assignment->setStartsAt((new \DateTimeImmutable($startsAt))->getTimestamp());
		$assignment->setLastRunAt((new \DateTimeImmutable($lastRunAt))->getTimestamp());
		$assignment->setTimezone($timezone);
		self::assertEquals($expected, $assignment->isDueToRun(new \DateTimeImmutable($now)));
	}

	public function isDueDataProvider(): array {
		return [
			['FREQ=DAILY', '@0', '@0', 'UTC', '1970-01-02T00:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '@0', 'UTC', '1970-01-01T08:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T08:00:00Z', 'UTC', '1970-01-01T08:00:01Z', false],
			['FREQ=DAILY;BYHOUR=8', '@0', '@0', '+0200', '1970-01-01T10:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '1970-01-01T10:00:00Z', false],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '1970-01-02T10:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '1970-01-02T10:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '2027-01-02T10:00:00Z', true],
		];
	}
}
