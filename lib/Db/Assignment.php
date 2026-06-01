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
use Recurr\Exception\InvalidRRule;
use Recurr\RecurrenceCollection;
use Recurr\Rule;
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
			new Rule($recurrence);
		} catch (InvalidRRule $e) {
			throw new \InvalidArgumentException('Invalid recurrence rule: ' . $recurrence, previous: $e);
		}
		$this->setter('recurrence', [$recurrence]);
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
			$startsAt = new \DateTime('@' . $this->getStartsAt());
			$lastRunAt = new \DateTime('@' . $this->getLastRunAt());
			// Find recurrences after the last run or after the current time if this assignment has never run
			$rule = new Rule($this->getRecurrence(), $startsAt, timezone: $this->getTimezone());
			$transformer = new \Recurr\Transformer\ArrayTransformer();
			$constraint = $this->getLastRunAt() !== 0 ? new AfterConstraint($lastRunAt, false) : new AfterConstraint($startsAt, true);
			/** @var RecurrenceCollection $collection */
			$collection = $transformer->transform($rule, $constraint);
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
