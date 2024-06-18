<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Anupam Kumar <kyteinsky@gmail.com>
 *
 * @author Anupam Kumar <kyteinsky@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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

	public static $columns = [
		'id',
		'session_id',
		'role',
		'content',
		'timestamp',
	];
	public static $fields = [
		'id',
		'sessionId',
		'role',
		'content',
		'timestamp',
	];

	public function __construct() {
		$this->addType('session_id', Types::INTEGER);
		$this->addType('role', Types::STRING);
		$this->addType('content', Types::STRING);
		$this->addType('timestamp', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'session_id' => $this->sessionId,
			'role' => $this->role,
			'content' => $this->content,
			'timestamp' => $this->timestamp,
		];
	}
}
