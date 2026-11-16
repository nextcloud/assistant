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
			['FREQ=DAILY;BYHOUR=0,8,23;BYMINUTE=0,59;BYSECOND=60'],
			['FREQ=MONTHLY;BYMONTHDAY=-1,1,31'],
			['FREQ=MONTHLY;BYDAY=MO,TU;BYSETPOS=-1'],
			['FREQ=YEARLY;BYMONTH=1,12;BYYEARDAY=-366,366;BYWEEKNO=-53,53'],
		];
	}

	public function testSetRecurrenceDoesNotEvaluateRule(): void {
		// Recurr never terminates when evaluating this rule, so setRecurrence() must not evaluate it
		$assignment = new Assignment();
		$assignment->setRecurrence('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30');
		self::assertSame('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30', $assignment->getRecurrence());
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
			// accepted by the Rule constructor
			['FREQ=WEEKLY;BYDAY=XX', 'BYDAY values must be weekdays'],
			['FREQ=MONTHLY;BYDAY=1XX', 'BYDAY values must be weekdays'],
			['FREQ=WEEKLY;BYDAY=XYZ', 'BYDAY values must be weekdays'],
			['FREQ=WEEKLY;BYDAY=MO,', 'BYDAY values must be weekdays'],
			['FREQ=DAILY;BYHOUR=XX', 'BYHOUR must contain integers'],
			['FREQ=DAILY;BYHOUR=24', 'BYHOUR values must be between 0 and 23'],
			['FREQ=DAILY;BYMINUTE=-1', 'BYMINUTE values must be between 0 and 59'],
			['FREQ=YEARLY;BYMONTH=13', 'BYMONTH values must be between 1 and 12'],
			['FREQ=MONTHLY;BYMONTHDAY=32', 'BYMONTHDAY values must be between -31 and 31, but not 0'],
			['FREQ=MONTHLY;BYMONTHDAY=0', 'BYMONTHDAY values must be between -31 and 31, but not 0'],
			['FREQ=MONTHLY;BYMONTHDAY=1.5', 'BYMONTHDAY must contain integers'],
			['FREQ=YEARLY;BYWEEKNO=54', 'BYWEEKNO values must be between -53 and 53, but not 0'],
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
