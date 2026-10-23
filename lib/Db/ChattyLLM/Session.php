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
 * @method \void setAssignmentId(?int $id)
 * @method \int|null getAssignmentId()
 * @method \string|null getAgentUserId()
 * @method \void setAgentUserId(?string $agentUserId)
 * @method \string|null getRoomToken()
 * @method \void setRoomToken(?string $roomToken)
 * @method \int|null getThreadId()
 * @method \void setThreadId(?int $threadId)
 * @method \string|null getAgentSeenRoomToken()
 * @method \void setAgentSeenRoomToken(?string $agentSeenRoomToken)
 * @method \int|null getAgentSeenCommentId()
 * @method \void setAgentSeenCommentId(?int $agentSeenCommentId)
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

	/**
	 * Session can be linked to assignments that run in this session
	 * @var ?int
	 */
	protected $assignmentId;

	/**
	 * When the session is used by an agent through Talk: the agent's NC user id
	 * @var ?string
	 */
	protected $agentUserId;

	/**
	 * When the session is used by an agent through Talk: the token of the 1-1 Talk room
	 * @var ?string
	 */
	protected $roomToken;

	/**
	 * When the session is used by an agent through Talk: the id of the Talk thread for this session
	 * @var ?int
	 */
	protected $threadId;

	/**
	 * The Talk room + comment of the last 👀 reaction of the agent on a human message,
	 * replaced by ✅ or 🔴 once the requested message generation completed/failed
	 * @var ?string
	 */
	protected $agentSeenRoomToken;

	/** @var ?int */
	protected $agentSeenCommentId;

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
		'assignment_id',
		'agent_user_id',
		'room_token',
		'thread_id',
		'agent_seen_room_token',
		'agent_seen_comment_id',
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
		'assignmentId',
		'agentUserId',
		'roomToken',
		'threadId',
		'agentSeenRoomToken',
		'agentSeenCommentId',
	];

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('title', Types::STRING);
		$this->addType('timestamp', Types::INTEGER);
		$this->addType('agencyConversationToken', Types::STRING);
		$this->addType('agencyPendingActions', Types::STRING);
		$this->addType('summary', Types::TEXT);
		$this->addType('isSummaryUpToDate', Types::SMALLINT);
		$this->addType('isRemembered', Types::SMALLINT);
		$this->addType('assignmentId', Types::BIGINT);
		$this->addType('agentUserId', Types::STRING);
		$this->addType('roomToken', Types::STRING);
		$this->addType('threadId', Types::BIGINT);
		$this->addType('agentSeenRoomToken', Types::STRING);
		$this->addType('agentSeenCommentId', Types::BIGINT);
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
			'is_summary_up_to_date' => $this->getIsSummaryUpToDate(),
			'is_remembered' => $this->getIsRemembered(),
			'assignment_id' => $this->getAssignmentId(),
			'agent_user_id' => $this->getAgentUserId(),
			'room_token' => $this->getRoomToken(),
			'thread_id' => $this->getThreadId(),
			'agent_seen_room_token' => $this->getAgentSeenRoomToken(),
			'agent_seen_comment_id' => $this->getAgentSeenCommentId(),
		];
	}

	public function setIsSummaryUpToDate(bool $value): void {
		$this->setter('isSummaryUpToDate', [$value ? 1 : 0]);
	}

	public function setIsRemembered(bool $value): void {
		$this->setter('isRemembered', [$value ? 1 : 0]);
	}

	public function getIsSummaryUpToDate(): bool {
		return $this->getter('isSummaryUpToDate') === 1;
	}

	public function getIsRemembered(): bool {
		return $this->getter('isRemembered') === 1;
	}
}
