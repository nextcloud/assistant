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
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setRecurrence(string $recurrence): void {
		if (trim($recurrence) !== '') {
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
			} catch (\InvalidArgumentException $e) {
				throw new \InvalidArgumentException('Invalid recurrence rule "' . $recurrence . '": ' . $e->getMessage(), previous: $e);
			}
		}
		$this->setter('recurrence', [$recurrence]);
	}

	/**
	 * Accepts rules as people and LLMs write them: spaces around the parts, a trailing ";"
	 * and an UNTIL without "Z". RRule requires UNTIL in UTC when the start has a timezone,
	 * so such an UNTIL is read in the assignment's timezone and converted to UTC.
	 */
	private static function normalizeRule(string $recurrence, string $timezone): string {
		$parts = [];
		foreach (explode(';', $recurrence) as $part) {
			if (trim($part) === '') {
				continue;
			}
			if (str_contains($part, '=')) {
				[$name, $value] = explode('=', $part, 2);
				$name = trim($name);
				$value = trim($value);
				if (strtoupper($name) === 'UNTIL') {
					$value = self::untilInUtc($value, new \DateTimeZone($timezone));
				}
				$part = $name . '=' . $value;
			}
			$parts[] = trim($part);
		}
		return implode(';', $parts);
	}

	private static function untilInUtc(string $until, \DateTimeZone $timezone): string {
		if (preg_match('/^\d{8}$/', $until) === 1) {
			// a date without a time includes the whole day
			$until .= 'T235959';
		} elseif (preg_match('/^\d{8}T\d{6}$/', $until) !== 1) {
			// already in UTC, or invalid and rejected by RRule
			return $until;
		}
		$date = \DateTime::createFromFormat('!Ymd\THis', $until, $timezone);
		if ($date === false) {
			return $until;
		}
		return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
	}

	/**
	 * The rule is evaluated from this start. RRule rejects a rule string with its own DTSTART when a start is given.
	 */
	private static function startDate(int $startsAt, string $timezone): \DateTime {
		return (new \DateTime('@' . $startsAt))->setTimezone(new \DateTimeZone($timezone));
	}

	/**
	 * Checks the BY* values against RFC 5545. RRule validates them as well,
	 * these checks give more specific error messages.
	 *
	 * @throws \InvalidArgumentException
	 */
	private static function validateRuleParts(string $recurrence): void {
		$parts = [];
		foreach (explode(';', $recurrence) as $part) {
			[$name, $value] = array_pad(explode('=', $part, 2), 2, '');
			$parts[strtoupper(trim($name))] = trim($value);
		}
		$values = static fn (string $name): array => isset($parts[$name]) ? explode(',', $parts[$name]) : [];

		// the position is the nth weekday of the month for MONTHLY rules, of the year otherwise
		$maxDayPosition = strtoupper($parts['FREQ'] ?? '') === 'MONTHLY' ? 5 : 53;
		foreach ($values('BYDAY') as $day) {
			if (preg_match('/^([+-]?\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/', $day, $matches) !== 1) {
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
			$rule = new RRule(self::normalizeRule($this->getRecurrence(), $this->getTimezone()), $start);
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
