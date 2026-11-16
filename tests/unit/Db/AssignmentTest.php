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
			['FREQ=DAILY', '1970-01-02T00:00:00Z', '@0', 'UTC', '1970-01-02T00:00:00Z', true],
			['FREQ=DAILY', '@0', '@0', 'UTC', '1970-01-02T00:00:00Z', false],
			['FREQ=DAILY;BYHOUR=8', '@0', '@0', 'UTC', '1970-01-01T08:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T08:00:00Z', 'UTC', '1970-01-01T08:00:01Z', false],
			['FREQ=DAILY;BYHOUR=8', '@0', '@0', '+0200', '1970-01-01T10:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '1970-01-01T10:00:00Z', false],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '1970-01-02T10:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '1970-01-02T10:00:00Z', true],
			['FREQ=DAILY;BYHOUR=8', '@0', '1970-01-01T10:00:00Z', '+0200', '2027-01-02T10:00:00Z', true],
		];
	}

	/**
	 * @dataProvider validRecurrenceDataProvider
	 */
	public function testSetValidRecurrence(string $rrule): void {
		$assignment = new Assignment();
		$assignment->setRecurrence($rrule);
		self::assertSame($rrule, $assignment->getRecurrence());
	}

	public function validRecurrenceDataProvider(): array {
		return [
			['FREQ=DAILY'],
			['FREQ=WEEKLY;BYDAY=MO'],
			['FREQ=WEEKLY;BYDAY=MO,WE,FR;BYHOUR=8'],
			['FREQ=MONTHLY;BYDAY=-1FR'],
			['FREQ=DAILY;COUNT=3'],
			['FREQ=DAILY;UNTIL=19700105T000000Z'],
		];
	}

	/**
	 * @dataProvider invalidRecurrenceDataProvider
	 */
	public function testSetInvalidRecurrence(string $rrule, string $expectedReason): void {
		$assignment = new Assignment();
		try {
			$assignment->setRecurrence($rrule);
			self::fail('Expected an InvalidArgumentException for ' . $rrule);
		} catch (\InvalidArgumentException $e) {
			self::assertStringContainsString($rrule, $e->getMessage());
			self::assertStringContainsString($expectedReason, $e->getMessage());
		}
		self::assertNull($assignment->getRecurrence());
	}

	public function invalidRecurrenceDataProvider(): array {
		return [
			// accepted by the Rule constructor, only rejected on evaluation
			['FREQ=WEEKLY;BYDAY=XX', 'Day is not a valid weekday'],
			['FREQ=MONTHLY;BYDAY=1XX', 'Day is not a valid weekday'],
			// rejected by the Rule constructor
			['FREQ=NOPE', 'FREQ is invalid'],
			['FREQ=DAILY;INTERVAL=0', 'Interval must be a positive integer'],
		];
	}

	public function testSetInvalidTimezone(): void {
		$assignment = new Assignment();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid timezone: Not/AZone');
		$assignment->setTimezone('Not/AZone');
	}
}
