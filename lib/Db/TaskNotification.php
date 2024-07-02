<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Julien Veyssier <julien-nc@posteo.net>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Db;

use OCP\AppFramework\Db\Entity;

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
		$this->addType('ocp_task_id', 'int');
		$this->addType('timestamp', 'int');
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
