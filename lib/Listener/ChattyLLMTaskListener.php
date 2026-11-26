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
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AgentTalkService;
use OCA\Assistant\Service\AssignmentsService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<TaskSuccessfulEvent|TaskFailedEvent>
 */
class ChattyLLMTaskListener implements IEventListener {

	public function __construct(
		private MessageMapper $messageMapper,
		private SessionMapper $sessionMapper,
		private TaskProcessingService $taskProcessingService,
		private AgentTalkService $agentTalkService,
		private AssignmentsService $assignmentsService,
		private LoggerInterface $logger,
		private NotificationService $notificationService,
		private ChatService $chatService,
	) {
	}

	public function handle(Event $event): void {
		// the event is dispatched in the task processing worker, an exception here would stop the worker
		try {
			$this->handleTaskEvent($event);
		} catch (\Exception $e) {
			$this->logger->error('Failed to handle the result of a chat task', ['exception' => $e]);
		}
	}

	/**
	 * Get the chat session a task writes to, null if it does not belong to the user of the task.
	 * The app ID and the custom ID of a task are chosen by whoever schedules it.
	 */
	private function getTaskSession(Task $task, int $sessionId): ?Session {
		$userId = $task->getUserId();
		if ($userId === null) {
			return null;
		}
		try {
			return $this->sessionMapper->getUserSession($userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			// also when the user deleted the session while the task was running
			$this->logger->info('Ignoring a chat task for a session that does not belong to its user', [
				'taskId' => $task->getId(),
				'userId' => $userId,
				'sessionId' => $sessionId,
			]);
			return null;
		}
	}

	private function handleTaskEvent(Event $event): void {
		if ($event instanceof TaskFailedEvent) {
			$task = $event->getTask();
			$customId = $task->getCustomId();
			if ($customId === null || $task->getAppId() !== (Application::APP_ID . ':chatty-llm')) {
				return;
			}
			if (preg_match('/^chatty-llm:(\d+)/', $customId, $matches)) {
				$sessionId = (int)$matches[1];
				$session = $this->getTaskSession($task, $sessionId);
				if ($session !== null && $session->getAssignmentId() !== null && $session->getRequesterUserId() === null) {
					// assignments ordered through an agent in Talk are not
					// notified: the failure is posted in the task's Talk thread
					$this->notificationService->sendAssignmentNotification(
						$task->getUserId(), $task, $session
					);
				}
				// in the agent-as-a-user feature, a failure notice is posted
				// in the agent 1-1 conversation when the generation failed
				try {
					$this->agentTalkService->markGenerationFailed($task->getUserId(), $sessionId);
				} catch (\Throwable $e) {
					$this->logger->error('Failed to mark the agent message as failed in Talk', ['exception' => $e]);
				}
			}
			return;
		}
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
			if ($this->getTaskSession($task, $sessionId) === null) {
				return;
			}
			$title = $this->chatService->cleanGeneratedTitle($task->getUserId(), $task->getOutput()['output'] ?? '');
			// keep the current title if nothing usable was generated
			if ($title !== '') {
				$this->sessionMapper->updateSessionTitle($task->getUserId(), $sessionId, $title);
			}
		}

		// message generation
		if (preg_match('/^chatty-llm:(\d+)/', $customId, $matches)) {
			$sessionId = (int)$matches[1];
			$session = $this->getTaskSession($task, $sessionId);
			if ($session === null) {
				return;
			}

			// concurrent task processing executors can process the same task twice and
			// dispatch this event twice, in which case the message was already stored
			try {
				$this->messageMapper->getMessageByTaskId($sessionId, $task->getId());
				return;
			} catch (DoesNotExistException $e) {
				// the message was not stored yet, continue with the normal handling
			}

			$isAgency = class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentInteraction')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\ContextAgentInteraction::ID;
			$isAudioChat = class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID;
			$isAgencyAudioChat = class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentAudioInteraction')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID;
			$isMultimodalChat = class_exists('OCP\\TaskProcessing\\TaskTypes\\MultimodalChatWithTools')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\MultimodalChatWithTools::ID;
			$isMultimodalAgencyChat = class_exists('OCP\\TaskProcessing\\TaskTypes\\MultimodalContextAgentInteraction')
				&& $taskTypeId === \OCP\TaskProcessing\TaskTypes\MultimodalContextAgentInteraction::ID;

			$taskOutput = $task->getOutput();

			// the transcript of the human's audio input, posted in Talk for agent
			// sessions; not on confirmation follow-ups, where the same audio was
			// already transcribed when it was first sent. The generated speech
			// audio of the answer is posted as a Talk voice message in any case.
			$inputTranscript = null;
			$audioFileId = null;
			if ($isAudioChat || $isAgencyAudioChat) {
				$isConfirmation = $this->isConfirmationFollowUp($task);
				$inputTranscript = $isConfirmation ? null : trim($taskOutput['input_transcript'] ?? '');
				if (trim($taskOutput['output_transcript'] ?? '') !== '' && is_numeric($taskOutput['output'] ?? null)) {
					$audioFileId = (int)$taskOutput['output'];
				}
			}
			// the generated files of a multimodal answer, posted as Talk file shares
			$outputFileIds = [];
			if ($isMultimodalChat || $isMultimodalAgencyChat) {
				$outputAttachments = $taskOutput['output_attachments'] ?? [];
				foreach (is_array($outputAttachments) ? $outputAttachments : [] as $outputAttachment) {
					if (is_numeric($outputAttachment)) {
						$outputFileIds[] = (int)$outputAttachment;
					}
				}
			}

			$message = new Message();
			$message->setSessionId($sessionId);
			$message->setOcpTaskId($task->getId());
			$message->setRole(Message::ROLE_ASSISTANT);
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
					// update session title if it's the first human message (the session also contains a system message)
					// and the user did not set a title manually while the response was generated:
					// the title of an audio-first session stays empty until we have the transcript
					$isFirstHumanMessage = !$this->messageMapper->hasHumanMessagesBefore($sessionId, $queryMessageId);
					// read the title from the database, the user may have set one while the task was running
					$currentTitle = $isFirstHumanMessage
						? $this->sessionMapper->getUserSession($task->getUserId(), $sessionId)->getTitle()
						: null;
					if ($isFirstHumanMessage && trim($currentTitle ?? '') === '') {
						$this->sessionMapper->updateSessionTitle($task->getUserId(), $sessionId, $this->chatService->truncateTitle($queryMessageContent));
						// the frontend can only generate a title from a text message, generate it now that we have the transcript
						if ($queryMessageContent !== '' && $this->chatService->isAutoTitleGenerationEnabled()) {
							$this->scheduleTitleGeneration($task->getUserId(), $sessionId);
						}
					}
				}
			} else {
				$content = trim($taskOutput['output'] ?? '');
				$message->setContent($content);
				$reasoning = trim($taskOutput['reasoning'] ?? '');
				$message->setReasoning($reasoning);
				// the task is not an audio one, but we might still need to Tts the answer
				// if it is a response to a ContextAgentInteraction confirmation that was asked about an audio message
				$this->runTtsIfNeeded($sessionId, $message, $taskTypeId, $task->getUserId());
				if ($isMultimodalChat || $isMultimodalAgencyChat) {
					$attachments = $taskOutput['output_attachments'] ?? [];
					$attachments = array_map(function ($attachment) use ($task) {
						return [
							'type' => 'File',
							'file_id' => $attachment,
							'ocp_task_id' => $task->getId(),
						];
					}, $attachments);
					$message->setAttachments(json_encode($attachments));
				}
			}
			try {
				$this->messageMapper->insert($message);
			} catch (\OCP\DB\Exception $e) {
				$this->logger->error('Message insertion error in chattyllm task listener', ['exception' => $e]);
			}

			// re-read the session, the user may have changed it while this task was handled
			$session = $this->sessionMapper->getUserSession($task->getUserId(), $sessionId);

			// store the conversation token and the actions if we are using the agency feature
			$pendingActions = null;
			if ($isAgency || $isAgencyAudioChat || $isMultimodalAgencyChat) {
				$conversationToken = ($taskOutput['conversation_token'] ?? null) ?: null;
				$pendingActions = ($taskOutput['actions'] ?? null) ?: null;
				$session->setAgencyConversationToken($conversationToken);
				$session->setAgencyPendingActions($pendingActions);
			}
			// Set flag that the conversation summary needs to be regenerated
			$session->setIsSummaryUpToDate(false);

			$assignmentId = $session->getAssignmentId();
			if ($assignmentId !== null && $session->getRequesterUserId() === null) {
				// assignments ordered through an agent in Talk are not
				// notified: the run output is posted in the task's Talk thread
				$this->notificationService->sendAssignmentNotification(
					$task->getUserId(), $task, $session
				);
			}

			$this->sessionMapper->update($session);

			if ($session->getRequesterUserId() === null) {
				return;
			}

			// ids of the scheduled tasks created by this agent turn, carried by
			// the structured task output; their presence means the turn has just
			// been approved and the tasks were created
			$scheduledTaskIds = [];
			if ($isAgency || $isAgencyAudioChat || $isMultimodalAgencyChat) {
				$outputScheduledTaskIds = $taskOutput['scheduled_task_ids'] ?? null;
				if (is_array($outputScheduledTaskIds)) {
					foreach ($outputScheduledTaskIds as $outputScheduledTaskId) {
						if (is_numeric($outputScheduledTaskId)) {
							$scheduledTaskIds[] = (int)$outputScheduledTaskId;
						}
					}
				}
			}

			// the requester and the 1-1 room are recorded on the created tasks;
			// the task's Talk thread itself is opened by its first run
			$isAssignmentRun = $assignmentId !== null;
			if ($scheduledTaskIds !== []) {
				$this->assignmentsService->linkRequesterToCreatedScheduledTasks($session, $scheduledTaskIds);
			}

			$replyContent = $message->getContent();
			if ($isAssignmentRun
				&& !$this->isConfirmationFollowUp($task)
				&& ($runTimestamp = $this->getAssignmentRunTimestamp($sessionId)) !== null) {
				// each run message of the task thread carries the time of the run
				$replyContent = $this->agentTalkService->getAssignmentRunHeader($runTimestamp, (string)$session->getRequesterUserId())
					. "\n\n" . $replyContent;
			}

			// in the agent-as-a-user feature, the generated message is posted
			// in the 1-1 Talk conversation with the human user
			$this->agentTalkService->postAgentTalkReply(
				$session,
				$replyContent,
				$pendingActions,
				$inputTranscript !== '' ? $inputTranscript : null,
				$audioFileId,
				$outputFileIds,
			);

			if ($isAssignmentRun && $session->getThreadId() !== null) {
				// the run marker (or the reply itself) opened or recreated the
				// task's Talk thread: store it as the delivery surface
				$this->assignmentsService->storeScheduledTaskThread($session, [$assignmentId]);
			}
		}
	}

	/**
	 * Whether the task is the follow-up of an agency confirmation (the
	 * sensitive actions were approved or rejected in the same session) rather
	 * than a fresh generation: the follow-up continues the same turn, it
	 * carries no run header.
	 */
	private function isConfirmationFollowUp(Task $task): bool {
		$taskInput = $task->getInput();
		return isset($taskInput['confirmation']) && (int)$taskInput['confirmation'] !== 0;
	}

	/**
	 * The start time of the assignment run whose output is being handled: the
	 * timestamp of the run prompt message, created when the run was scheduled.
	 */
	private function getAssignmentRunTimestamp(int $sessionId): ?int {
		try {
			return $this->messageMapper->getLastHumanMessage($sessionId)->getTimestamp();
		} catch (\Throwable $e) {
			$this->logger->warning('Could not find the prompt message of an assignment run', [
				'exception' => $e,
				'sessionId' => $sessionId,
			]);
			return null;
		}
	}

	private function scheduleTitleGeneration(?string $userId, int $sessionId): void {
		try {
			$this->chatService->scheduleTitleGeneration($userId, $sessionId);
		} catch (\Throwable $e) {
			// not fatal: the session keeps the transcript as its title
			$this->logger->warning('Failed to schedule the title generation of a chat session', [
				'exception' => $e,
				'sessionId' => $sessionId,
			]);
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
