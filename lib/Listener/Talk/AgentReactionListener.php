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
 * @template-implements IEventListener<\OCA\Talk\Events\ReactionAddedEvent>
 *
 * Handles the reactions of human users on confirmation-related agent Talk messages:
 * they are sent back to the agent context as approve/reject signals.
 *
 * @psalm-suppress InvalidTemplateParam, ImplementedParamTypeMismatch
 */
class AgentReactionListener implements IEventListener {

	public function __construct(
		private AgentTalkService $agentTalkService,
		private \OCA\Assistant\Service\AgentsService $agentsService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!class_exists('OCA\Talk\Events\ReactionAddedEvent')
			|| !$event instanceof \OCA\Talk\Events\ReactionAddedEvent) {
			return;
		}
		try {
			$reaction = $event->getReaction();
			if ($event->getActorType() !== 'users') {
				return;
			}
			// ignore reactions added by agent users themselves
			if ($this->agentsService->getAgentByUserId($event->getActorId()) !== null) {
				return;
			}
			$comment = $event->getMessage();
			$referenceId = $comment->getReferenceId();
			if (!preg_match('/^' . AgentTalkService::CONFIRMATION_REFERENCE_PREFIX . '(\d+)(:[0-9]+)?$/', $referenceId ?? '', $matches)) {
				return;
			}
			if (!in_array($reaction, [AgentTalkService::REACTION_APPROVE, AgentTalkService::REACTION_REJECT], true)) {
				return;
			}
			$this->agentTalkService->handleConfirmationReaction(
				$event->getActorId(),
				(int)$matches[1],
				(int)$comment->getId(),
				$reaction,
				$event->getRoom(),
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to react to a Talk reaction for an agent', ['exception' => $e]);
		}
	}
}
