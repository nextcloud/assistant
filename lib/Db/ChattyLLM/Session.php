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
 * @method \string getUserId()
 * @method \void setUserId(string $userId)
 * @method \?string getTitle()
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
