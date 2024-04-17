<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method \string getUserId()
 * @method \void setUserId(string $userId)
 * @method \string getOutput()
 * @method \void setOutput(string $value)
 * @method \string getAppId()
 * @method \void setAppId(string $appId)
 * @method \int getOcpTaskId()
 * @method \void setOcpTaskId(int $value)
 * @method \int getTimestamp()
 * @method \void setTimestamp(int $timestamp)
 * @method \string getTaskType()
 * @method \void setTaskType(string $taskType)
 * @method \void setStatus(int $status)
 * @method \int getStatus()
 * @method \void setCategory(int $category)
 * @method \int getCategory()
 * @method \string getInputs()
 * @method \void setInputs(string $inputs)
 * @method \string getIdentifier()
 * @method \void setIdentifier(string $identifier)
 */
class MetaTask extends Entity implements \JsonSerializable {
	/** @var string */
	protected $userId;
	/** @var string */
	protected $inputs;
	/** @var string */
	protected $output;
	/** @var string */
	protected $appId;
	/** @var int */
	protected $ocpTaskId;
	/** @var int */
	protected $timestamp;
	/** @var string */
	protected $taskType;
	/** @var int */
	protected $status;
	/** @var int */
	protected $category;
	/** @var string */
	protected $identifier;

	public function __construct() {
		$this->addType('user_id', 'string');
		$this->addType('inputs', 'string');
		$this->addType('output', 'string');
		$this->addType('app_id', 'string');
		$this->addType('ocp_task_id', 'integer');
		$this->addType('timestamp', 'integer');
		$this->addType('task_type', 'string');
		$this->addType('status', 'integer');
		$this->addType('category', 'integer');
		$this->addType('identifier', 'string');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'inputs' => $this->getInputsAsArray(),
			'output' => $this->output,
			'app_id' => $this->appId,
			'ocp_task_id' => $this->ocpTaskId,
			'task_type' => $this->taskType,
			'timestamp' => $this->timestamp,
			'status' => $this->status,
			'category' => $this->category,
			'identifier' => $this->identifier,
		];
	}

	#[\ReturnTypeWillChange]
	public function jsonSerializeCc() {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'inputs' => $this->getInputsAsArray(),
			'output' => $this->output,
			'appId' => $this->appId,
			'ocpTaskId' => $this->ocpTaskId,
			'taskType' => $this->taskType,
			'timestamp' => $this->timestamp,
			'status' => $this->status,
			'category' => $this->category,
			'identifier' => $this->identifier,
		];
	}

	/**
	 * @return array
	 */
	public function getInputsAsArray(): array {
		if ($this->inputs === null) {
			return [];
		}
		return json_decode($this->inputs, true) ?? [];
	}
}
