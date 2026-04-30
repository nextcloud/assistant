<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \string getUserId()
 * @method \void setUserId(string $userId)
 * @method \string getPrompt()
 * @method \void setPrompt(string $prompt)
 * @method \string getRecurrence()
 * @method \void setRecurrence(string $recurrence)
 * @method \int getStartsAt()
 * @method \void setStartsAt(int $startsAt)
 * @method \int getCreatedAt()
 * @method \void setCreatedAt(int $createdAt)
 * @method \int getUpdatedAt()
 * @method \void setUpdatedAt(int $updatedAt)
 * @method \void setLastRunAt(int $lastRunAt)
 * @method \int getLastRunAt()
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

	public static $columns = [
		'id',
		'user_id',
		'prompt',
		'recurrence',
		'starts_at',
		'created_at',
		'updated_at',
		'last_run_at',
	];
	public static $fields = [
		'id',
		'user_id',
		'prompt',
		'recurrence',
		'startsAt',
		'createdAt',
		'updatedAt',
		'lastRunAt',
	];

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('prompt', Types::STRING);
		$this->addType('recurrence', Types::STRING);
		$this->addType('startsAt', Types::INTEGER);
		$this->addType('createdAt', Types::STRING);
		$this->addType('updatedAt', Types::STRING);
		$this->addType('lastRunAt', Types::INTEGER);
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
		];
	}

	/**
	 * Evaluates the recurrence rule and checks if a run is due
	 */
	public function isDueToRun(\DateTimeImmutable $now): bool {
		// TODO: Use an actual algorithm here
		return true;
	}
}
