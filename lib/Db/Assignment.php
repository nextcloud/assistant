<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use RRule\RRule;
use function OCP\Log\logger;

/**
 * @method \string getUserId()
 * @method \void setUserId(string $userId)
 * @method \string getPrompt()
 * @method \void setPrompt(string $prompt)
 * @method \string getRecurrence()
 * @method \int getStartsAt()
 * @method \void setStartsAt(int $startsAt)
 * @method \int getCreatedAt()
 * @method \void setCreatedAt(int $createdAt)
 * @method \int getUpdatedAt()
 * @method \void setUpdatedAt(int $updatedAt)
 * @method \void setLastRunAt(int $lastRunAt)
 * @method \int getLastRunAt()
 * @method \string getTimezone()
 */
class Assignment extends Entity implements \JsonSerializable {
	/** @var string */
	protected $userId;
	/** @var string */
	protected $prompt;
	/** @var string */
	protected $recurrence;
	/** @var int */
	protected $startsAt;
	/** @var int */
	protected $createdAt;
	/** @var int */
	protected $updatedAt;
	/** @var int */
	protected $lastRunAt;

	/** @var string */
	protected $timezone;

	public static $columns = [
		'id',
		'user_id',
		'prompt',
		'recurrence',
		'starts_at',
		'created_at',
		'updated_at',
		'last_run_at',
		'timezone'
	];
	public static $fields = [
		'id',
		'userId',
		'prompt',
		'recurrence',
		'startsAt',
		'createdAt',
		'updatedAt',
		'lastRunAt',
		'timezone'
	];

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('prompt', Types::STRING);
		$this->addType('recurrence', Types::STRING);
		$this->addType('startsAt', Types::BIGINT);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
		$this->addType('lastRunAt', Types::BIGINT);
		$this->addType('timezone', Types::STRING);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user_id' => $this->getUserId(),
			'prompt' => $this->getPrompt(),
			'recurrence' => $this->getRecurrence(),
			'starts_at' => $this->getStartsAt(),
			'created_at' => $this->getCreatedAt(),
			'updated_at' => $this->getUpdatedAt(),
			'last_run_at' => $this->getLastRunAt(),
			'timezone' => $this->getTimezone(),
		];
	}

	/**
	 * Rejects invalid rules and rules that never occur after the start.
	 * An empty rule means the assignment runs once, at its start.
	 * The rule is stored without a DTSTART and without the "RRULE:" prefix.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setRecurrence(string $recurrence): void {
		$recurrence = trim($recurrence);
		if ($recurrence !== '') {
			try {
				self::validateRuleParts($recurrence);
				// startsAt and timezone are still null if they are set after the recurrence
				/** @psalm-suppress RedundantPropertyInitializationCheck */
				$timezone = $this->timezone ?? 'UTC';
				/** @psalm-suppress RedundantPropertyInitializationCheck */
				$start = self::startDate($this->startsAt ?? time(), $timezone);
				// RRule stops searching after a full calendar cycle without an occurrence
				$rule = new RRule(self::normalizeRule($recurrence, $timezone), $start);
				if ($rule->getOccurrencesAfter($start, true, 1) === []) {
					throw new \InvalidArgumentException('the rule never occurs');
				}
			} catch (\InvalidArgumentException|\DateMalformedStringException $e) {
				// RRule parses dates like UNTIL with DateTime, which throws DateMalformedStringException
				throw new \InvalidArgumentException('Invalid recurrence rule "' . $recurrence . '": ' . $e->getMessage(), previous: $e);
			}
			// the DTSTART is ignored, so it is not stored either
			$recurrence = self::withoutStart($recurrence);
		}
		$this->setter('recurrence', [$recurrence]);
	}

	/**
	 * Accepts rules as people and LLMs write them: spaces around the parts, a trailing ";",
	 * numbers with leading zeros (BYHOUR=08) and an UNTIL without "Z". RRule requires UNTIL in UTC
	 * when the start has a timezone, so such an UNTIL is read in the assignment's timezone and converted to UTC.
	 *
	 * @throws \InvalidArgumentException
	 */
	private static function normalizeRule(string $recurrence, string $timezone): string {
		$parts = [];
		foreach (explode(';', self::withoutStart($recurrence)) as $part) {
			if (trim($part) === '') {
				continue;
			}
			if (str_contains($part, '=')) {
				[$name, $value] = explode('=', $part, 2);
				$name = trim($name);
				$value = trim($value);
				if (strtoupper($name) === 'UNTIL') {
					$value = self::untilInUtc($value, new \DateTimeZone($timezone));
				} elseif (preg_match('/^[+-]?\d+(,[+-]?\d+)*$/', $value) === 1) {
					// RRule rejects leading zeros, "08" becomes "8" and "00" becomes "0"
					$value = preg_replace('/(?<!\d)0+(?=\d)/', '', $value);
				}
				$part = $name . '=' . $value;
			}
			$parts[] = trim($part);
		}
		return implode(';', $parts);
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private static function untilInUtc(string $until, \DateTimeZone $timezone): string {
		if (preg_match('/^\d{8}$/', $until) === 1) {
			// a date without a time includes the whole day
			$until .= 'T235959';
		} elseif (preg_match('/^\d{8}T\d{6}$/', $until) !== 1) {
			// already in UTC, or invalid and rejected by RRule
			return $until;
		}
		$date = \DateTime::createFromFormat('!Ymd\THis', $until, $timezone);
		// an invalid date like month 13 only gives a warning and rolls over into the next year
		$errors = \DateTime::getLastErrors();
		if ($date === false || ($errors !== false && $errors['warning_count'] > 0)) {
			throw new \InvalidArgumentException('UNTIL is not a valid date: ' . $until);
		}
		return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
	}

	/**
	 * The rule is evaluated from the assignment's start, so a DTSTART in the rule is ignored:
	 * both as its own line ("DTSTART:20260101T090000Z\nRRULE:FREQ=DAILY") and as a part ("DTSTART=...;FREQ=DAILY").
	 * Returns the rule parts joined with ";", without the "RRULE:" prefix.
	 */
	private static function withoutStart(string $recurrence): string {
		$parts = [];
		foreach (preg_split('/\R/', $recurrence) ?: [] as $line) {
			$line = trim($line);
			if (preg_match('/^DTSTART[:;]/i', $line) === 1) {
				continue;
			}
			if (stripos($line, 'RRULE:') === 0) {
				$line = substr($line, strlen('RRULE:'));
			}
			foreach (explode(';', $line) as $part) {
				if (stripos(trim($part), 'DTSTART=') !== 0) {
					$parts[] = $part;
				}
			}
		}
		return implode(';', $parts);
	}

	private static function startDate(int $startsAt, string $timezone): \DateTime {
		return (new \DateTime('@' . $startsAt))->setTimezone(new \DateTimeZone($timezone));
	}

	/**
	 * RRule walks and caches every occurrence from the start. For SECONDLY, MINUTELY and HOURLY rules this
	 * moves the start to about one day before the last run, by whole intervals on the local wall clock as RRule
	 * steps them. Rules with COUNT keep their start, their occurrences are counted from it.
	 */
	private static function fastForwardStart(RRule $rule, \DateTime $start, int $lastRunAt): \DateTime {
		$parts = $rule->getRule();
		$unit = ['SECONDLY' => 1, 'MINUTELY' => 60, 'HOURLY' => 3600][$parts['FREQ']] ?? null;
		if ($unit === null || !empty($parts['COUNT'])) {
			return $start;
		}
		$step = max(1, (int)$parts['INTERVAL']) * $unit;
		$utc = new \DateTimeZone('UTC');
		$timezone = $start->getTimezone();
		$wallStart = new \DateTimeImmutable($start->format('Y-m-d H:i:s'), $utc);
		$wallLastRun = new \DateTimeImmutable((new \DateTime('@' . $lastRunAt))->setTimezone($timezone)->format('Y-m-d H:i:s'), $utc);
		// one day of margin, so a DST change cannot move the new start past the last run
		$elapsed = $wallLastRun->getTimestamp() - 86400 - $wallStart->getTimestamp();
		for ($intervals = intdiv(max(0, $elapsed), $step); $intervals > 0; $intervals--) {
			$wall = $wallStart->modify('+' . ($intervals * $step) . ' seconds')->format('Y-m-d H:i:s');
			$newStart = new \DateTime($wall, $timezone);
			// a wall time in a DST gap does not exist, so take the interval before it
			if ($newStart->format('Y-m-d H:i:s') === $wall) {
				return $newStart;
			}
		}
		return $start;
	}

	/**
	 * Checks the BY* values against RFC 5545. RRule validates them as well,
	 * these checks give more specific error messages.
	 *
	 * @throws \InvalidArgumentException
	 */
	private static function validateRuleParts(string $recurrence): void {
		$parts = [];
		foreach (explode(';', self::withoutStart($recurrence)) as $part) {
			[$name, $value] = array_pad(explode('=', $part, 2), 2, '');
			$parts[strtoupper(trim($name))] = trim($value);
		}
		$values = static fn (string $name): array => isset($parts[$name]) ? explode(',', $parts[$name]) : [];

		// the position is the nth weekday of the month for MONTHLY rules, of the year otherwise
		$maxDayPosition = strtoupper($parts['FREQ'] ?? '') === 'MONTHLY' ? 5 : 53;
		foreach ($values('BYDAY') as $day) {
			if (preg_match('/^([+-]?\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/', strtoupper($day), $matches) !== 1) {
				throw new \InvalidArgumentException('BYDAY values must be weekdays (SU, MO, ...) with an optional position, e.g. MO or -1FR');
			}
			if ($matches[1] !== '') {
				$position = abs((int)$matches[1]);
				if ($position < 1 || $position > $maxDayPosition) {
					throw new \InvalidArgumentException('BYDAY position must be between -' . $maxDayPosition . ' and ' . $maxDayPosition . ', but not 0');
				}
			}
		}

		// part name => [min, max, negative values allowed]
		$ranges = [
			'BYSECOND' => [0, 60, false],
			'BYMINUTE' => [0, 59, false],
			'BYHOUR' => [0, 23, false],
			'BYMONTH' => [1, 12, false],
			'BYMONTHDAY' => [1, 31, true],
			'BYYEARDAY' => [1, 366, true],
			'BYWEEKNO' => [1, 53, true],
			'BYSETPOS' => [1, 366, true],
		];
		foreach ($ranges as $name => [$min, $max, $negativeAllowed]) {
			foreach ($values($name) as $value) {
				if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
					throw new \InvalidArgumentException($name . ' must contain integers');
				}
				$number = (int)$value;
				$magnitude = $negativeAllowed ? abs($number) : $number;
				if ($magnitude < $min || $magnitude > $max) {
					throw new \InvalidArgumentException($name . ' values must be between ' . ($negativeAllowed ? '-' . $max . ' and ' . $max . ', but not 0' : $min . ' and ' . $max));
				}
			}
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function setTimezone(string $timezone): void {
		try {
			$tz = new \DateTimeZone($timezone);
		} catch (\Throwable $e) {
			throw new \InvalidArgumentException('Invalid timezone: ' . $timezone, previous: $e);
		}
		$this->setter('timezone', [$tz->getName()]);
	}

	/**
	 * Evaluates the recurrence rule and checks if a run is due
	 */
	public function isDueToRun(\DateTimeImmutable $now): bool {
		if (trim($this->getRecurrence()) === '') {
			// runs once, at the start
			return $this->getLastRunAt() === 0 && $this->getStartsAt() <= $now->getTimestamp();
		}
		try {
			$start = self::startDate($this->getStartsAt(), $this->getTimezone());
			$recurrence = self::normalizeRule($this->getRecurrence(), $this->getTimezone());
			$rule = new RRule($recurrence, $start);
			if ($this->getLastRunAt() !== 0) {
				$rule = new RRule($recurrence, self::fastForwardStart($rule, $start, $this->getLastRunAt()));
			}
			// Find the first recurrence after the last run or after the start if this assignment has never run
			$next = $this->getLastRunAt() !== 0
				? $rule->getOccurrencesAfter(new \DateTime('@' . $this->getLastRunAt()), false, 1)
				: $rule->getOccurrencesAfter($start, true, 1);
			if ($next === []) {
				return false;
			}
			$nextRecurrence = $next[0];
			$isDue = $nextRecurrence->getTimestamp() <= $now->getTimestamp() && $nextRecurrence->getTimestamp() > $this->getLastRunAt();
			logger('assistant')->debug('Next recurrence of assignment ' . $this->getId() . ' of user ' . $this->getUserId() . ': ' . $nextRecurrence->format('Y-m-d H:i:s') . ' - isDue: ' . ($isDue ? 'true' : 'false'));
			return $isDue;
		} catch (\Exception $e) {
			// this should not happen, as we validate the rule on setRecurrence, but just in case, we catch the exception and log it
			logger('assistant')->error($e->getMessage(), ['exception' => $e]);
		}
		return false;
	}
}
