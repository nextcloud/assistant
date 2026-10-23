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
 * @template-implements IEventListener<\OCA\Talk\Events\ChatMessageSentEvent>
 *
 * Handles messages sent by users in Talk conversations with agents:
 * the agent answer is generated in a classic Assistant chat session
 * and posts back in the 1-1 Talk conversation with the human user.
 */
class AgentMentionListener implements IEventListener {

	public function __construct(
		private AgentTalkService $agentTalkService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!class_exists('OCA\Talk\Events\ChatMessageSentEvent')
			|| !$event instanceof \OCA\Talk\Events\ChatMessageSentEvent) {
			return;
		}
		try {
			$this->agentTalkService->handleIncomingTalkMessage($event->getRoom(), $event->getComment());
		} catch (\Throwable $e) {
			$this->logger->error('Failed to react to a Talk chat message for an agent', ['exception' => $e]);
		}
	}
}
