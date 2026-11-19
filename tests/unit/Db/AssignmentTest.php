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
			// more than 732 occurrences between the start and the last run
			['FREQ=DAILY;BYHOUR=8', '2020-01-01T00:00:00Z', '2026-01-01T08:00:00Z', 'UTC', '2026-01-02T08:00:00Z', true],
			['FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29', '2026-03-01T00:00:00Z', '@0', 'UTC', '2028-02-29T00:00:00Z', true],
			['FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29', '2026-03-01T00:00:00Z', '@0', 'UTC', '2028-02-28T00:00:00Z', false],
			// COUNT also includes the occurrences before the last run
			['FREQ=DAILY;COUNT=3', '2026-01-01T00:00:00Z', '2026-01-02T00:00:00Z', 'UTC', '2026-01-03T00:00:01Z', true],
			['FREQ=DAILY;COUNT=3', '2026-01-01T00:00:00Z', '2026-01-03T00:00:00Z', 'UTC', '2026-01-04T00:00:01Z', false],
			// the next occurrence after this one is 3 years later
			['FREQ=MONTHLY;INTERVAL=36;BYDAY=MO;BYSETPOS=1', '2026-01-01T00:00:00Z', '@0', 'UTC', '2026-01-05T00:00:01Z', true],
			['FREQ=HOURLY;BYHOUR=8;BYSETPOS=1', '2026-01-01T00:00:00Z', '@0', 'UTC', '2026-01-01T08:00:00Z', true],
			// an empty rule runs once, at the start
			['', '2026-01-01T00:00:00Z', '@0', 'UTC', '2025-12-31T23:59:59Z', false],
			['', '2026-01-01T00:00:00Z', '@0', 'UTC', '2026-01-01T00:00:00Z', true],
			['', '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z', 'UTC', '2027-01-01T00:00:00Z', false],
		];
	}

	/**
	 * Rules written loosely may already be stored, the start is in the past so a past UNTIL is accepted
	 *
	 * @dataProvider looseRuleDataProvider
	 */
	public function testIsDueForLooselyWrittenRule(string $rrule, string $lastRunAt, string $now, bool $expected): void {
		$assignment = Assignment::fromRow([
			'recurrence' => $rrule,
			// 09:00 in Berlin
			'starts_at' => (new \DateTimeImmutable('2026-01-01T08:00:00Z'))->getTimestamp(),
			'last_run_at' => (new \DateTimeImmutable($lastRunAt))->getTimestamp(),
			'timezone' => 'Europe/Berlin',
		]);
		self::assertSame($expected, $assignment->isDueToRun(new \DateTimeImmutable($now)));
	}

	public function looseRuleDataProvider(): array {
		return [
			['FREQ=DAILY;', '2026-01-01T08:00:00Z', '2026-01-02T08:00:00Z', true],
			[' FREQ=DAILY ; INTERVAL=2 ', '2026-01-01T08:00:00Z', '2026-01-02T08:00:00Z', false],
			[' FREQ=DAILY ; INTERVAL=2 ', '2026-01-01T08:00:00Z', '2026-01-03T08:00:00Z', true],
			// an UNTIL date includes the whole day in the assignment's timezone
			['FREQ=DAILY;UNTIL=20260102', '2026-01-01T08:00:00Z', '2026-01-02T08:00:00Z', true],
			['FREQ=DAILY;UNTIL=20260102', '2026-01-02T08:00:00Z', '2026-01-03T08:00:00Z', false],
			// 08:59 in Berlin is before the 09:00 occurrence, in UTC it would be after it
			['FREQ=DAILY;UNTIL=20260102T085900', '2026-01-01T08:00:00Z', '2026-01-02T08:00:00Z', false],
			['FREQ=DAILY;UNTIL=20260102T090000', '2026-01-01T08:00:00Z', '2026-01-02T08:00:00Z', true],
			// numbers with leading zeros
			['FREQ=DAILY;BYHOUR=09;BYMINUTE=00', '2026-01-01T08:00:00Z', '2026-01-02T08:00:00Z', true],
			['FREQ=DAILY;INTERVAL=02', '2026-01-01T08:00:00Z', '2026-01-03T08:00:00Z', true],
		];
	}

	/**
	 * Frequent rules are not walked from the start, the occurrences after the last run stay the same
	 *
	 * @dataProvider frequentRuleDataProvider
	 */
	public function testIsDueForFrequentRuleStartedLongAgo(string $rrule, string $startsAt, string $lastRunAt, string $timezone, string $now, bool $expected): void {
		$assignment = Assignment::fromRow([
			'recurrence' => $rrule,
			'starts_at' => (new \DateTimeImmutable($startsAt))->getTimestamp(),
			'last_run_at' => (new \DateTimeImmutable($lastRunAt))->getTimestamp(),
			'timezone' => $timezone,
		]);
		self::assertSame($expected, $assignment->isDueToRun(new \DateTimeImmutable($now)));
	}

	public function frequentRuleDataProvider(): array {
		return [
			// walking all occurrences from the start needs more than 512 MB
			['FREQ=MINUTELY', '2023-11-18T00:00:00Z', '2026-11-18T11:59:30Z', 'UTC', '2026-11-18T12:00:00Z', true],
			// 217440 minutes from the start to June 1st, the occurrences are at 217434 and 217441
			['FREQ=MINUTELY;INTERVAL=7', '2026-01-01T00:00:00Z', '2026-06-01T00:00:30Z', 'UTC', '2026-06-01T00:00:59Z', false],
			['FREQ=MINUTELY;INTERVAL=7', '2026-01-01T00:00:00Z', '2026-06-01T00:00:30Z', 'UTC', '2026-06-01T00:01:00Z', true],
			// every 5 hours on the Berlin wall clock from 09:00: after the DST change on April 10th at 08:00 and 13:00 (11:00Z)
			['FREQ=HOURLY;INTERVAL=5', '2026-01-01T08:00:00Z', '2026-04-10T06:30:00Z', 'Europe/Berlin', '2026-04-10T10:59:59Z', false],
			['FREQ=HOURLY;INTERVAL=5', '2026-01-01T08:00:00Z', '2026-04-10T06:30:00Z', 'Europe/Berlin', '2026-04-10T11:00:00Z', true],
			// COUNT also includes the occurrences before the last run
			['FREQ=HOURLY;COUNT=3', '2026-01-01T00:00:00Z', '2026-01-01T02:00:00Z', 'UTC', '2026-01-03T00:00:00Z', false],
		];
	}

	/**
	 * Rules that never occur may already be stored, from before they were rejected on save
	 *
	 * @dataProvider neverOccurringRuleDataProvider
	 */
	public function testIsDueTerminatesForStoredRuleThatNeverOccurs(string $rrule): void {
		$assignment = Assignment::fromRow([
			'recurrence' => $rrule,
			'starts_at' => (new \DateTimeImmutable('2026-01-01T00:00:00Z'))->getTimestamp(),
			'last_run_at' => 0,
			'timezone' => 'UTC',
		]);
		self::assertFalse($assignment->isDueToRun(new \DateTimeImmutable('2026-06-01T00:00:00Z')));
	}

	/**
	 * The rule runs from the assignment's start, a DTSTART in the rule is ignored
	 *
	 * @dataProvider ruleWithStartDataProvider
	 */
	public function testIsDueIgnoresStartInStoredRule(string $rrule): void {
		$assignment = Assignment::fromRow([
			'recurrence' => $rrule,
			'starts_at' => (new \DateTimeImmutable('2026-01-01T09:00:00Z'))->getTimestamp(),
			'last_run_at' => (new \DateTimeImmutable('2026-01-01T09:00:00Z'))->getTimestamp(),
			'timezone' => 'UTC',
		]);
		self::assertFalse($assignment->isDueToRun(new \DateTimeImmutable('2026-01-02T08:59:59Z')));
		self::assertTrue($assignment->isDueToRun(new \DateTimeImmutable('2026-01-02T09:00:00Z')));
	}

	public function ruleWithStartDataProvider(): array {
		return [
			["DTSTART:20200101T000000Z\nRRULE:FREQ=DAILY"],
			["DTSTART:20200101T000000Z\r\nRRULE:FREQ=DAILY\r\n"],
			["DTSTART;TZID=Europe/Berlin:20200101T000000\nRRULE:FREQ=DAILY"],
			['DTSTART=20200101T000000Z;FREQ=DAILY'],
			['FREQ=DAILY;DTSTART=20200101T000000Z'],
			['RRULE:FREQ=DAILY'],
		];
	}

	public function neverOccurringRuleDataProvider(): array {
		return [
			['FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30'],
			['FREQ=MONTHLY;BYMONTH=2;BYMONTHDAY=30'],
			['FREQ=HOURLY;BYMONTH=2;BYMONTHDAY=30'],
			['FREQ=SECONDLY;BYMONTH=2;BYMONTHDAY=30'],
			// 2026-01-01 is a Thursday, so a Monday is never hit
			['FREQ=DAILY;INTERVAL=7;BYDAY=MO'],
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
			['FREQ=MINUTELY;COUNT=10000'],
			['FREQ=DAILY;BYHOUR=0,8,23;BYMINUTE=0,59;BYSECOND=60'],
			['FREQ=MONTHLY;BYMONTHDAY=-1,1,31'],
			['FREQ=MONTHLY;BYDAY=MO,TU;BYSETPOS=-1'],
			['FREQ=YEARLY;BYMONTH=1,12'],
			['FREQ=YEARLY;BYYEARDAY=-366,366'],
			['FREQ=YEARLY;BYWEEKNO=-53,53;BYDAY=MO'],
			['FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29'],
			['FREQ=MONTHLY;BYDAY=5MO'],
			['FREQ=MONTHLY;BYDAY=-5SU'],
			['FREQ=YEARLY;BYDAY=53FR'],
			['FREQ=YEARLY;BYDAY=-53TH'],
			['FREQ=WEEKLY;BYDAY=MO,WE,FR'],
			['FREQ=HOURLY;BYHOUR=8;BYSETPOS=1'],
			// stored as given
			[''],
			['FREQ=DAILY;'],
			['FREQ=DAILY; INTERVAL=2'],
			['FREQ=DAILY;UNTIL=20991231'],
			['FREQ=DAILY;UNTIL=20991231T120000'],
			['FREQ=DAILY;BYHOUR=08;BYMINUTE=00'],
			['FREQ=MONTHLY;BYMONTHDAY=01,-01;INTERVAL=02;COUNT=010'],
			['FREQ=WEEKLY;BYDAY=mo,We'],
			['FREQ=MONTHLY;BYDAY=-1fr'],
		];
	}

	/**
	 * @dataProvider cleanedUpRecurrenceDataProvider
	 */
	public function testSetRecurrenceStoresCleanedUpRule(string $rrule, string $expected): void {
		$assignment = new Assignment();
		$assignment->setRecurrence($rrule);
		self::assertSame($expected, $assignment->getRecurrence());
	}

	public function cleanedUpRecurrenceDataProvider(): array {
		return [
			// trimmed
			['   ', ''],
			[" FREQ=DAILY\n", 'FREQ=DAILY'],
			// without DTSTART and the "RRULE:" prefix
			["DTSTART:20200101T000000Z\nRRULE:FREQ=DAILY", 'FREQ=DAILY'],
			["DTSTART:20200101T000000Z\r\nRRULE:FREQ=DAILY;BYHOUR=8\r\n", 'FREQ=DAILY;BYHOUR=8'],
			["DTSTART;TZID=Europe/Berlin:20200101T000000\nRRULE:FREQ=DAILY", 'FREQ=DAILY'],
			['DTSTART=20200101T000000Z;FREQ=DAILY', 'FREQ=DAILY'],
			['FREQ=DAILY;DTSTART=20200101T000000Z;INTERVAL=2', 'FREQ=DAILY;INTERVAL=2'],
			['RRULE:FREQ=DAILY', 'FREQ=DAILY'],
		];
	}

	/**
	 * @dataProvider neverOccurringRuleDataProvider
	 */
	public function testSetRecurrenceRejectsRuleThatNeverOccurs(string $rrule): void {
		$assignment = new Assignment();
		$assignment->setStartsAt((new \DateTimeImmutable('2026-01-01T00:00:00Z'))->getTimestamp());
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('the rule never occurs');
		$assignment->setRecurrence($rrule);
	}

	public function testSetRecurrenceRejectsRuleThatEnded(): void {
		$assignment = new Assignment();
		$assignment->setStartsAt((new \DateTimeImmutable('2026-01-01T00:00:00Z'))->getTimestamp());
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('the rule never occurs');
		$assignment->setRecurrence('FREQ=DAILY;UNTIL=20251231T000000Z');
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
			// checked by Assignment
			['FREQ=WEEKLY;BYDAY=XX', 'BYDAY values must be weekdays'],
			['FREQ=MONTHLY;BYDAY=1XX', 'BYDAY values must be weekdays'],
			['FREQ=WEEKLY;BYDAY=XYZ', 'BYDAY values must be weekdays'],
			['FREQ=WEEKLY;BYDAY=MO,', 'BYDAY values must be weekdays'],
			['FREQ=MONTHLY;BYDAY=0MO', 'BYDAY position must be between -5 and 5, but not 0'],
			['FREQ=MONTHLY;BYDAY=6MO', 'BYDAY position must be between -5 and 5, but not 0'],
			['FREQ=MONTHLY;BYDAY=99MO', 'BYDAY position must be between -5 and 5, but not 0'],
			['FREQ=YEARLY;BYDAY=0MO', 'BYDAY position must be between -53 and 53, but not 0'],
			['FREQ=YEARLY;BYDAY=54MO', 'BYDAY position must be between -53 and 53, but not 0'],
			['FREQ=DAILY;BYHOUR=XX', 'BYHOUR must contain integers'],
			['FREQ=DAILY;BYHOUR=24', 'BYHOUR values must be between 0 and 23'],
			['FREQ=DAILY;BYMINUTE=-1', 'BYMINUTE values must be between 0 and 59'],
			['FREQ=YEARLY;BYMONTH=13', 'BYMONTH values must be between 1 and 12'],
			['FREQ=MONTHLY;BYMONTHDAY=32', 'BYMONTHDAY values must be between -31 and 31, but not 0'],
			['FREQ=MONTHLY;BYMONTHDAY=0', 'BYMONTHDAY values must be between -31 and 31, but not 0'],
			['FREQ=MONTHLY;BYMONTHDAY=1.5', 'BYMONTHDAY must contain integers'],
			['FREQ=YEARLY;BYWEEKNO=54', 'BYWEEKNO values must be between -53 and 53, but not 0'],
			['FREQ=MONTHLY;BYDAY=6mo', 'BYDAY position must be between -5 and 5, but not 0'],
			['FREQ=SECONDLY;COUNT=40000000', 'COUNT must not be greater than 10000'],
			['FREQ=MINUTELY;COUNT=010001', 'COUNT must not be greater than 10000'],
			['FREQ=DAILY;UNTIL=20991301', 'UNTIL is not a valid date'],
			['FREQ=DAILY;UNTIL=20991231T250000', 'UNTIL is not a valid date'],
			// the rule is still checked when it has a DTSTART
			["DTSTART:20260101T000000Z\nRRULE:FREQ=WEEKLY;BYDAY=XX", 'BYDAY values must be weekdays'],
			["DTSTART:20260101T000000Z\nRRULE:FREQ=NOPE", 'The FREQ rule part must be one of'],
			// a rule with only a DTSTART is not stored as an empty rule, which would run once
			['DTSTART:20260101T000000Z', 'malformed RRULE property'],
			// checked by RRule
			['FREQ=NOPE', 'The FREQ rule part must be one of'],
			['FREQ=DAILY;INTERVAL=0', 'The INTERVAL rule part must be a positive integer'],
			['FREQ=WEEKLY;BYMONTHDAY=1', 'The BYMONTHDAY rule part MUST NOT be specified when the FREQ rule part is set to WEEKLY'],
			['FREQ=DAILY;UNTIL=20991231T250000Z', 'Failed to parse time string'],
		];
	}

	public function testSetInvalidTimezone(): void {
		$assignment = new Assignment();
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid timezone: Not/AZone');
		$assignment->setTimezone('Not/AZone');
	}
}
