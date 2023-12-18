<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TPAssistant\Db\Text2Image;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getValue()
 * @method void setValue(string $value)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 */
class Prompt extends Entity implements \JsonSerializable {
	/** @var string */
	protected $userId;
	/** @var string */
	protected $value;
	/** @var int */
	protected $timestamp;

	public function __construct() {
		$this->addType('user_id', 'string');
		$this->addType('value', 'string');
		$this->addType('timestamp', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'value' => $this->value,
			'timestamp' => $this->timestamp,
		];
	}
}
