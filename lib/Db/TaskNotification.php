<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getOcpTaskId()
 * @method \void setOcpTaskId(int $ocpTaskId)
 * @method \int getTimestamp()
 * @method \void setTimestamp(int $timestamp)
 */
class TaskNotification extends Entity implements \JsonSerializable {
	/** @var int */
	protected $ocpTaskId;
	/** @var int */
	protected $timestamp;


	public function __construct() {
		$this->addType('ocp_task_id', Types::INTEGER);
		$this->addType('timestamp', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'ocp_task_id' => $this->ocpTaskId,
			'timestamp' => $this->timestamp,
		];
	}
}
