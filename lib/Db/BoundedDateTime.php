<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

/**
 * Start date for evaluating a recurrence rule with a bounded search.
 *
 * Recurr's ArrayTransformer never terminates for rules without occurrences
 * (e.g. FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30), it only has a loop guard for YEARLY rules.
 * It clones the rule's start date and moves the clone forward with modify() and setDate(),
 * so this class stops the search once it passes the deadline or the maximum number of steps.
 */
class BoundedDateTime extends \DateTime {
	private int $steps = 0;

	public function __construct(
		string $datetime,
		private \DateTimeInterface $deadline,
		private ?int $maxSteps = null,
	) {
		parent::__construct($datetime);
	}

	/**
	 * @throws \OverflowException
	 */
	#[\ReturnTypeWillChange]
	public function modify(string $modifier) {
		$result = parent::modify($modifier);
		$this->checkBounds();
		return $result;
	}

	/**
	 * @throws \OverflowException
	 */
	#[\ReturnTypeWillChange]
	public function setDate(int $year, int $month, int $day) {
		$result = parent::setDate($year, $month, $day);
		$this->checkBounds();
		return $result;
	}

	/**
	 * @throws \OverflowException
	 */
	private function checkBounds(): void {
		$this->steps++;
		if ($this > $this->deadline || ($this->maxSteps !== null && $this->steps > $this->maxSteps)) {
			throw new \OverflowException('Recurrence search limit reached');
		}
	}
}
