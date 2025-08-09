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
 * @method \int getSessionId()
 * @method \void setSessionId(int $sessionId)
 * @method \string getRole()
 * @method \void setRole(string $role)
 * @method \string getContent()
 * @method \void setContent(string $content)
 * @method \int getTimestamp()
 * @method \void setTimestamp(int $timestamp)
 * @method \int getOcpTaskId()
 * @method \void setOcpTaskId(int $ocpTaskId)
 * @method \string getSources()
 * @method \void setSources(string $sources)
 * @method \string getAttachments()
 * @method \void setAttachments(string $attachments)
 */
class Message extends Entity implements \JsonSerializable {
	/** @var int */
	protected $sessionId;
	/** @var string */
	protected $role;
	/** @var string */
	protected $content;
	/** @var int */
	protected $timestamp;
	/** @var int */
	protected $ocpTaskId;
	/** @var string */
	protected $sources;
	/** @var string */
	protected $attachments;

	public static $columns = [
		'id',
		'session_id',
		'role',
		'content',
		'timestamp',
		'ocp_task_id',
		'sources',
		'attachments',
	];
	public static $fields = [
		'id',
		'sessionId',
		'role',
		'content',
		'timestamp',
		'ocpTaskId',
		'sources',
		'attachments',
	];

	public function __construct() {
		$this->addType('session_id', Types::INTEGER);
		$this->addType('role', Types::STRING);
		$this->addType('content', Types::STRING);
		$this->addType('timestamp', Types::INTEGER);
		$this->addType('ocp_task_id', Types::INTEGER);
		$this->addType('sources', Types::STRING);
		$this->addType('attachments', Types::STRING);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'session_id' => $this->sessionId,
			'role' => $this->role,
			'content' => $this->content,
			'timestamp' => $this->timestamp,
			'ocp_task_id' => $this->ocpTaskId,
			'sources' => $this->sources,
			'attachments' => $this->attachments === null
				? []
				: (json_decode($this->attachments, true) ?: []),
		];
	}
}
