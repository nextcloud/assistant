<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Recurr\Exception as RecurrException;
use Recurr\Exception\InvalidRRule;
use Recurr\RecurrenceCollection;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;
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
	private const OCCURRENCE_CHECK_YEARS = 10;
	// about 0.5s of searching, bounds SECONDLY/MINUTELY rules that occur very rarely
	private const OCCURRENCE_CHECK_MAX_STEPS = 100000;

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
	 * @throws \InvalidArgumentException
	 */
	public function setRecurrence(string $recurrence): void {
		try {
			$rule = new Rule($recurrence);
			self::validateRuleParts($rule);
			$this->validateRuleOccurs($rule);
		} catch (RecurrException $e) {
			throw new \InvalidArgumentException('Invalid recurrence rule "' . $recurrence . '": ' . $e->getMessage(), previous: $e);
		}
		$this->setter('recurrence', [$recurrence]);
	}

	/**
	 * Rejects rules that never occur, e.g. FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30
	 *
	 * @throws InvalidRRule
	 */
	private function validateRuleOccurs(Rule $rule): void {
		// startsAt is still null if the recurrence is set before the start
		/** @psalm-suppress RedundantPropertyInitializationCheck */
		$start = $this->startsAt ?? time();
		$deadline = (new \DateTimeImmutable('@' . $start))->modify('+' . self::OCCURRENCE_CHECK_YEARS . ' years');
		$rule->setStartDate(new BoundedDateTime('@' . $start, $deadline, self::OCCURRENCE_CHECK_MAX_STEPS));
		$config = new ArrayTransformerConfig();
		$config->setVirtualLimit(1);
		try {
			$occurs = !(new ArrayTransformer($config))->transform($rule)->isEmpty();
		} catch (\OverflowException) {
			$occurs = false;
		}
		if (!$occurs) {
			throw new InvalidRRule('the rule never occurs, or its first occurrence is too far from the start date');
		}
	}

	/**
	 * The Rule constructor only parses the string and does not check the BY* values.
	 * Evaluating a rule with invalid values throws, emits PHP warnings or never terminates,
	 * so the values are checked here against RFC 5545 without evaluating the rule.
	 *
	 * @throws InvalidRRule
	 */
	private static function validateRuleParts(Rule $rule): void {
		foreach ($rule->getByDay() ?? [] as $day) {
			if (preg_match('/^([+-]?\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/', (string)$day) !== 1) {
				throw new InvalidRRule('BYDAY values must be weekdays (SU, MO, ...) with an optional position, e.g. MO or -1FR');
			}
		}

		// part name => [values, min, max, negative values allowed]
		$parts = [
			'BYSECOND' => [$rule->getBySecond(), 0, 60, false],
			'BYMINUTE' => [$rule->getByMinute(), 0, 59, false],
			'BYHOUR' => [$rule->getByHour(), 0, 23, false],
			'BYMONTH' => [$rule->getByMonth(), 1, 12, false],
			'BYMONTHDAY' => [$rule->getByMonthDay(), 1, 31, true],
			'BYYEARDAY' => [$rule->getByYearDay(), 1, 366, true],
			'BYWEEKNO' => [$rule->getByWeekNumber(), 1, 53, true],
			'BYSETPOS' => [$rule->getBySetPosition(), 1, 366, true],
		];
		foreach ($parts as $name => [$values, $min, $max, $negativeAllowed]) {
			foreach ($values ?? [] as $value) {
				if (preg_match('/^[+-]?\d+$/', (string)$value) !== 1) {
					throw new InvalidRRule($name . ' must contain integers');
				}
				$number = (int)$value;
				$magnitude = $negativeAllowed ? abs($number) : $number;
				if ($magnitude < $min || $magnitude > $max) {
					throw new InvalidRRule($name . ' values must be between ' . ($negativeAllowed ? '-' . $max . ' and ' . $max . ', but not 0' : $min . ' and ' . $max));
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
		try {
			// The search stops at the deadline, so rules that never occur don't block the job.
			// It is far after $now because the search position of YEARLY rules can be months ahead of their occurrences.
			$startsAt = new BoundedDateTime('@' . $this->getStartsAt(), $now->modify('+2 years'));
			$lastRunAt = new \DateTime('@' . $this->getLastRunAt());
			// Find the first recurrence after the last run or after the start if this assignment has never run
			$rule = new Rule($this->getRecurrence(), $startsAt, timezone: $this->getTimezone());
			$config = new ArrayTransformerConfig();
			$config->setVirtualLimit(1);
			$transformer = new ArrayTransformer($config);
			$constraint = $this->getLastRunAt() !== 0 ? new AfterConstraint($lastRunAt, false) : new AfterConstraint($startsAt, true);
			try {
				/** @var RecurrenceCollection $collection */
				$collection = $transformer->transform($rule, $constraint, countConstraintFailures: false);
			} catch (\OverflowException) {
				return false;
			}
			if ($collection->isEmpty()) {
				return false;
			}
			$nextRecurrence = $collection->first();
			$isDue = $nextRecurrence->getStart()->getTimestamp() <= $now->getTimestamp() && $nextRecurrence->getStart()->getTimestamp() > $this->getLastRunAt();
			logger('assistant')->debug('Next recurrence of assignment ' . $this->getId() . ' of user ' . $this->getUserId() . ': ' . $nextRecurrence->getStart()->format('Y-m-d H:i:s') . ' - isDue: ' . ($isDue ? 'true' : 'false'));
			return $isDue;
		} catch (InvalidRRule|\Exception|NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			// this should not happen, as we validate the rule on setRecurrence, but just in case, we catch the exception and log it
			logger('assistant')->error($e->getMessage(), ['exception' => $e]);
		}
		return false;
	}
}
