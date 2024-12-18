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
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method \string|\null getTitle()
 * @method \void setTitle(?\string $title)
 * @method \int|\null getTimestamp()
 * @method \void setTimestamp(?\int $timestamp)
 * @method string|null getAgencyConversationToken()
 * @method void setAgencyConversationToken(?string $agencyConversationToken)
 * @method string|null getAgencyPendingActions()
 * @method void setAgencyPendingActions(?string $agencyPendingActions)
 */
class Session extends Entity implements \JsonSerializable {
	/** @var string */
	protected $userId;
	/** @var string */
	protected $title;
	/** @var int */
	protected $timestamp;
	/** @var string */
	protected $agencyConversationToken;
	/** @var string */
	protected $agencyPendingActions;

	public static $columns = [
		'id',
		'user_id',
		'title',
		'timestamp',
		'agency_conversation_token',
		'agency_pending_actions',
	];
	public static $fields = [
		'id',
		'userId',
		'title',
		'timestamp',
		'agencyConversationToken',
		'agencyPendingActions',
	];

	public function __construct() {
		$this->addType('user_id', Types::STRING);
		$this->addType('title', Types::STRING);
		$this->addType('timestamp', Types::INTEGER);
		$this->addType('agency_conversation_token', Types::STRING);
		$this->addType('agency_pending_actions', Types::STRING);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user_id' => $this->getUserId(),
			'title' => $this->getTitle(),
			'timestamp' => $this->getTimestamp(),
			'agency_conversation_token' => $this->getAgencyConversationToken(),
			'agency_pending_actions' => $this->getAgencyPendingActions(),
		];
	}
}
