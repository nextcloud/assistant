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
use OCA\Assistant\Service\TaskProcessingService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<TaskSuccessfulEvent>
 */
class ChattyLLMTaskListener implements IEventListener {

	public function __construct(
		private MessageMapper $messageMapper,
		private SessionMapper $sessionMapper,
		private TaskProcessingService $taskProcessingService,
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

			$taskOutput = $task->getOutput();

			$message = new Message();
			$message->setSessionId($sessionId);
			$message->setOcpTaskId($task->getId());
			$message->setRole('assistant');
			$message->setTimestamp(time());
			$sources = json_encode($taskOutput['sources'] ?? []);
			$message->setSources($sources ?: '[]');
			$message->setAttachments('[]');
			if ($isAudioChat || $isAgencyAudioChat) {
				$outputTranscript = trim($taskOutput['output_transcript'] ?? '');
				$message->setContent($outputTranscript);
				// agency might not return any output but just ask for confirmation
				if ($outputTranscript !== '') {
					$attachment = ['type' => 'Audio', 'file_id' => $taskOutput['output']];
					if (isset($taskOutput['audio_id'])) {
						$attachment['remote_audio_id'] = $taskOutput['audio_id'];
						if (isset($taskOutput['audio_expires_at'])) {
							$attachment['remote_audio_expires_at'] = $taskOutput['audio_expires_at'];
						}
					}
					$message->setAttachments(json_encode([$attachment]));
				}
				// now we have the transcription of the user audio input
				if (preg_match('/^chatty-llm:\d+:(\d+)$/', $customId, $matches)) {
					$queryMessageId = (int)$matches[1];
					$queryMessage = $this->messageMapper->getMessageById($sessionId, $queryMessageId);
					$queryMessageContent = trim($taskOutput['input_transcript'] ?? '');
					$queryMessage->setContent($queryMessageContent);
					$this->messageMapper->update($queryMessage);
					// update session title if it's the first message
					$olderMessages = $this->messageMapper->getMessagesBefore($sessionId, $queryMessage->getTimestamp());
					if (count($olderMessages) === 0) {
						$this->sessionMapper->updateSessionTitle($task->getUserId(), $sessionId, $queryMessageContent);
					}
				}
			} else {
				$content = trim($taskOutput['output'] ?? '');
				$message->setContent($content);
				// the task is not an audio one, but we might still need to Tts the answer
				// if it is a response to a ContextAgentInteraction confirmation that was asked about an audio message
				$this->runTtsIfNeeded($sessionId, $message, $taskTypeId, $task->getUserId());
			}
			try {
				$this->messageMapper->insert($message);
			} catch (\OCP\DB\Exception $e) {
				$this->logger->error('Message insertion error in chattyllm task listener', ['exception' => $e]);
			}

			// store the conversation token and the actions if we are using the agency feature
			if ($isAgency || $isAgencyAudioChat) {
				$session = $this->sessionMapper->getUserSession($task->getUserId(), $sessionId);
				$conversationToken = ($taskOutput['conversation_token'] ?? null) ?: null;
				$pendingActions = ($taskOutput['actions'] ?? null) ?: null;
				$session->setAgencyConversationToken($conversationToken);
				$session->setAgencyPendingActions($pendingActions);
				$this->sessionMapper->update($session);
			}
		}
	}

	/**
	 * Run TTS on the response of an agency confirmation message
	 *
	 * @param int $sessionId
	 * @param Message $message
	 * @param string $taskTypeId
	 * @param string|null $userId
	 * @return void
	 */
	private function runTtsIfNeeded(int $sessionId, Message $message, string $taskTypeId, ?string $userId): void {
		if (!class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentInteraction')
			|| $taskTypeId !== \OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID) {
			return;
		}
		// is the last non-empty user message an audio one?
		$lastNonEmptyMessage = $this->messageMapper->getLastNonEmptyHumanMessage($sessionId);
		$attachments = $lastNonEmptyMessage->jsonSerialize()['attachments'] ?? [];
		foreach ($attachments as $attachment) {
			if ($attachment['type'] === 'Audio') {
				// we found an audio attachment, response should be audio
				$this->runTtsTask($message, $userId);
				return;
			}
		}
	}

	/**
	 * @param Message $message
	 * @param string|null $userId
	 * @return void
	 */
	private function runTtsTask(Message $message, ?string $userId): void {
		try {
			/** @psalm-suppress UndefinedClass */
			$task = new Task(
				\OCP\TaskProcessing\TaskTypes\TextToSpeech::ID,
				['input' => $message->getContent()],
				Application::APP_ID . ':internal',
				$userId,
			);
			$ttsTaskOutput = $this->taskProcessingService->runTaskProcessingTask($task);
		} catch (\OCP\TaskProcessing\Exception\Exception $e) {
			$this->logger->warning('TTS sub-task failed for chat message.', [
				'exception' => $e,
				'messageId' => $message->getId(),
			]);
			return;
		}
		$speechFileId = $ttsTaskOutput['speech'];
		// we need to set "ocp_task_id" here because the file is not an output of the task that produced the message
		// and we need the task ID + the file ID to load the audio file in the frontend
		$attachment = [
			'type' => 'Audio',
			'file_id' => $speechFileId,
			'ocp_task_id' => $task->getId(),
		];
		$message->setAttachments(json_encode([$attachment]));
	}
}
