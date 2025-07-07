<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<TaskSuccessfulEvent>
 */
class ChattyLLMTaskListener implements IEventListener {

	public function __construct(
		private MessageMapper $messageMapper,
		private SessionMapper $sessionMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof TaskSuccessfulEvent)) {
			return;
		}

		$task = $event->getTask();
		$customId = $task->getCustomId();
		$appId = $task->getAppId();
		$taskTypeId = $task->getTaskTypeId();

		if ($customId === null || $appId !== (Application::APP_ID . ':chatty-llm')) {
			return;
		}

		// title generation
		if (preg_match('/^chatty-title:(\d+)$/', $customId, $matches)) {
			$sessionId = (int)$matches[1];
			$title = trim($task->getOutput()['output'] ?? '');
			$this->sessionMapper->updateSessionTitle($task->getUserId(), $sessionId, $title);
		}

		// message generation
		if (preg_match('/^chatty-llm:(\d+)/', $customId, $matches)) {
			$sessionId = (int)$matches[1];

			$isAgency = class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentInteraction')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID;
			$isAudioChat = class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID;
			$isAgencyAudioChat = class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentAudioInteraction')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID;

			$message = new Message();
			$message->setSessionId($sessionId);
			$message->setOcpTaskId($task->getId());
			$message->setRole('assistant');
			$message->setTimestamp(time());
			$sources = json_encode($task->getOutput()['sources'] ?? []);
			$message->setSources($sources ?: '[]');
			if ($isAudioChat || $isAgencyAudioChat) {
				$outputTranscript = trim($task->getOutput()['output_transcript'] ?? '');
				$message->setContent($outputTranscript);
				// agency might not return any output but just ask for confirmation
				if ($outputTranscript !== '') {
					$message->setAttachments('[{"type":"Audio","fileId":' . $task->getOutput()['output'] . '}]');
				}
				// now we have the transcription of the user audio input
				if (preg_match('/^chatty-llm:\d+:(\d+)$/', $customId, $matches)) {
					$queryMessageId = (int)$matches[1];
					$queryMessage = $this->messageMapper->getMessageById($queryMessageId);
					$queryMessage->setContent(trim($task->getOutput()['input_transcript'] ?? ''));
					$this->messageMapper->update($queryMessage);
				}
			} else {
				$message->setContent(trim($task->getOutput()['output'] ?? ''));
			}
			try {
				$this->messageMapper->insert($message);
			} catch (\OCP\DB\Exception $e) {
				$this->logger->error('Message insertion error in chattyllm task listener', ['exception' => $e]);
			}

			// store the conversation token and the actions if we are using the agency feature
			if ($isAgency || $isAgencyAudioChat) {
				$session = $this->sessionMapper->getUserSession($task->getUserId(), $sessionId);
				$conversationToken = ($task->getOutput()['conversation_token'] ?? null) ?: null;
				$pendingActions = ($task->getOutput()['actions'] ?? null) ?: null;
				$session->setAgencyConversationToken($conversationToken);
				$session->setAgencyPendingActions($pendingActions);
				$this->sessionMapper->update($session);
			}
		}
	}
}
