<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener\Talk;

use OCA\Assistant\Service\AgentTalkService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<\OCA\Talk\Events\ChatMessageSentEvent|\OCA\Talk\Events\SystemMessageSentEvent>
 *
 * Handles messages sent by users in Talk conversations with agents:
 * - chat messages (mentions in group rooms, direct messages in 1-1
 *   conversations) come as ChatMessageSentEvent
 * - file shares (voice messages, attachments) come as SystemMessageSentEvent:
 *   they are "file_shared" system messages
 *
 * The agent answer is generated in a classic Assistant chat session
 * and posts back in the 1-1 Talk conversation with the human user.
 *
 * @psalm-suppress InvalidTemplateParam, ImplementedParamTypeMismatch
 */
class AgentTalkMessageListener implements IEventListener {

	public function __construct(
		private AgentTalkService $agentTalkService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		$room = null;
		$comment = null;
		if (class_exists('OCA\Talk\Events\ChatMessageSentEvent')
			&& $event instanceof \OCA\Talk\Events\ChatMessageSentEvent) {
			$room = $event->getRoom();
			$comment = $event->getComment();
		} elseif (class_exists('OCA\Talk\Events\SystemMessageSentEvent')
			&& $event instanceof \OCA\Talk\Events\SystemMessageSentEvent) {
			$room = $event->getRoom();
			$comment = $event->getComment();
		} else {
			return;
		}
		try {
			$this->agentTalkService->handleIncomingTalkMessage($room, $comment);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to react to a Talk message for an agent', ['exception' => $e]);
		}
	}
}
