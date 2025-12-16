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
 * @method \string|null getSummary()
 * @method \void setSummary(?string $title)
 * @method \int getTimestamp()
 * @method \void setTimestamp(int $timestamp)
 * @method \string|null getAgencyConversationToken()
 * @method \void setAgencyConversationToken(?string $agencyConversationToken)
 * @method \string|null getAgencyPendingActions()
 * @method \void setAgencyPendingActions(?string $agencyPendingActions)
 * @method \int getIsRemembered()
 * @method \void setIsRemembered(int $isRemembered)
 * @method \int getIsSummaryUpToDate()
 * @method \void setIsSummaryUpToDate(int $isSummaryUpToDate)
 */
class Session extends Entity implements \JsonSerializable {
	/** @var string */
	protected $userId;
	/** @var ?string */
	protected $title;
	/** @var int */
	protected $timestamp;
	/** @var ?string */
	protected $agencyConversationToken;
	/** @var ?string */
	protected $agencyPendingActions;

	/**
	 * Will be used to inject into assistant memories upon calling LLM
	 *
	 * @var ?string
	 */
	protected $summary;

	/** @var int */
	protected $isSummaryUpToDate;

	/**
	 * Whether to remember the insights from this chat session across all chat sessions
	 *
	 * @var int
	 */
	protected $isRemembered;


	public static $columns = [
		'id',
		'user_id',
		'title',
		'timestamp',
		'agency_conversation_token',
		'agency_pending_actions',
		'summary',
		'is_summary_up_to_date',
		'is_remembered',
	];
	public static $fields = [
		'id',
		'userId',
		'title',
		'timestamp',
		'agencyConversationToken',
		'agencyPendingActions',
		'summary',
		'isSummaryUpToDate',
		'isRemembered',
	];

	public function __construct() {
		$this->addType('user_id', Types::STRING);
		$this->addType('title', Types::STRING);
		$this->addType('timestamp', Types::INTEGER);
		$this->addType('agency_conversation_token', Types::STRING);
		$this->addType('agency_pending_actions', Types::STRING);
		$this->addType('summary', Types::TEXT);
		$this->addType('is_summary_up_to_date', Types::SMALLINT);
		$this->addType('is_remembered', Types::SMALLINT);
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
			'summary' => $this->getSummary(),
			'is_summary_up_to_date' => $this->getIsSummaryUpToDate() !== 0,
			'is_remembered' => $this->getIsRemembered() !== 0,
		];
	}
}
