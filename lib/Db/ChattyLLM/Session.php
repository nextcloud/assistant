<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db\ChattyLLM;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \string getUserId()
 * @method \void setUserId(string $userId)
 * @method \string|null getTitle()
 * @method \void setTitle(?string $title)
 * @method \int|null getTimestamp()
 * @method \void setTimestamp(?int $timestamp)
 */
class Session extends Entity implements \JsonSerializable {
	/** @var string */
	protected $userId;
	/** @var string */
	protected $title;
	/** @var int */
	protected $timestamp;

	public static $columns = [
		'id',
		'user_id',
		'title',
		'timestamp',
	];
	public static $fields = [
		'id',
		'userId',
		'title',
		'timestamp',
	];

	public function __construct() {
		$this->addType('user_id', Types::STRING);
		$this->addType('title', Types::STRING);
		$this->addType('timestamp', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'title' => $this->title,
			'timestamp' => $this->timestamp,
		];
	}
}
