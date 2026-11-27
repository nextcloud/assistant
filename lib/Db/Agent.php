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
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string|null getPersonality()
 * @method void setPersonality(?string $personality)
 * @method string|null getScope()
 * @method void setScope(?string $scope)
 */
class Agent extends Entity implements \JsonSerializable {
	protected $userId;
	/** @var ?string */
	protected $personality;
	/** @var ?string */
	protected $scope;

	public static $columns = [
		'id',
		'user_id',
		'personality',
		'scope',
	];
	public static $fields = [
		'id',
		'userId',
		'personality',
		'scope',
	];

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('personality', Types::TEXT);
		$this->addType('scope', Types::TEXT);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user_id' => $this->getUserId(),
			'personality' => $this->getPersonality(),
			'scope' => $this->getScope(),
		];
	}
}
