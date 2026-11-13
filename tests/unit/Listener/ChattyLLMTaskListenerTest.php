<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Listener;

use OCA\Assistant\Db\ChattyLLM\Message;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Listener\ChattyLLMTaskListener;
use OCA\Assistant\Service\AgentTalkService;
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction;
use OCP\TaskProcessing\TaskTypes\MultimodalContextAgentInteraction;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Concurrent task processing executors can run the same task twice,
 * which must not store the chat message twice.
 */
class ChattyLLMTaskListenerTest extends TestCase {

	private const SESSION_ID = 23;
	private const TASK_ID = 91;

	private MessageMapper&MockObject $messageMapper;
	private SessionMapper&MockObject $sessionMapper;
	private ChattyLLMTaskListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->messageMapper = $this->createMock(MessageMapper::class);
		$this->sessionMapper = $this->createMock(SessionMapper::class);

		$session = new Session();
		$session->setAssignmentId(null);
		$this->sessionMapper->method('getUserSession')->willReturn($session);

		$this->rebuildListener($this->createMock(AgentTalkService::class));
	}

	private function rebuildListener(AgentTalkService $agentTalkService, ?SessionMapper $sessionMapper = null): void {
		$this->listener = new ChattyLLMTaskListener(
			$this->messageMapper,
			$sessionMapper ?? $this->sessionMapper,
			$this->createMock(TaskProcessingService::class),
			$agentTalkService,
			$this->createMock(LoggerInterface::class),
			$this->createMock(NotificationService::class),
		);
	}

	private function makeTask(): Task {
		$task = new Task(
			TextToTextChat::ID,
			[
				'input' => 'What is a cat?',
				'system_prompt' => '',
				'history' => [],
			],
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput(['output' => 'A cat is an animal.']);
		return $task;
	}

	private function handleTask(): void {
		$event = new TaskSuccessfulEvent($this->makeTask());
		$this->listener->handle($event);
	}

	public function testMessageIsStored(): void {
		$this->messageMapper->method('getMessageByTaskId')
			->with(self::SESSION_ID, self::TASK_ID)
			->willThrowException(new DoesNotExistException('Message not found'));
		$storedMessage = null;
		$this->messageMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (Message $message) use (&$storedMessage): Message {
				$storedMessage = $message;
				$message->setId(3);
				return $message;
			});
		$this->sessionMapper->expects($this->once())->method('update');

		$this->handleTask();

		$this->assertNotNull($storedMessage);
		$this->assertSame(self::SESSION_ID, $storedMessage->getSessionId());
		$this->assertSame(self::TASK_ID, $storedMessage->getOcpTaskId());
		$this->assertSame(Message::ROLE_ASSISTANT, $storedMessage->getRole());
		$this->assertSame('A cat is an animal.', $storedMessage->getContent());
	}

	public function testMessageIsStoredOnlyOnce(): void {
		$storedMessage = new Message();
		$storedMessage->setSessionId(self::SESSION_ID);
		$storedMessage->setOcpTaskId(self::TASK_ID);
		$this->messageMapper->method('getMessageByTaskId')
			->with(self::SESSION_ID, self::TASK_ID)
			->willReturn($storedMessage);
		$this->messageMapper->expects($this->never())->method('insert');
		$this->sessionMapper->expects($this->never())->method('update');

		$this->handleTask();
	}

	public function testTitleTasksAreUntouched(): void {
		$task = new Task(
			TextToTextChat::ID,
			[
				'input' => 'Generate a title',
				'system_prompt' => '',
				'history' => [],
			],
			'assistant:chatty-llm',
			'admin',
			'chatty-title:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput(['output' => 'Cats']);

		$event = new TaskSuccessfulEvent($task);
		$this->messageMapper->expects($this->never())->method('getMessageByTaskId');
		$this->messageMapper->expects($this->never())->method('insert');
		$this->sessionMapper->expects($this->once())
			->method('updateSessionTitle')
			->with('admin', self::SESSION_ID, 'Cats');

		$this->listener->handle($event);
	}

	private function makeAudioTask(array $input, array $output): Task {
		$task = new Task(
			ContextAgentAudioInteraction::ID,
			$input,
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput($output);
		return $task;
	}

	private function givenUnstoredMessage(): void {
		$this->messageMapper->method('getMessageByTaskId')
			->with(self::SESSION_ID, self::TASK_ID)
			->willThrowException(new DoesNotExistException('Message not found'));
		$this->messageMapper->method('insert')
			->willReturnCallback(static function (Message $message): Message {
				$message->setId(3);
				return $message;
			});
	}

	private function givenAgentSession(SessionMapper&MockObject $sessionMapper, Session $session): void {
		$sessionMapper->method('getUserSession')->willReturn($session);
		$sessionMapper->method('update');
	}

	public function testAgentReplyReceivesTheAudioTranscript(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = new Session();
		$session->setAssignmentId(null);
		$session->setRequesterUserId('alice');
		$this->givenAgentSession($sessionMapper, $session);

		$this->givenUnstoredMessage();

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				'The agent answer',
				null,
				'Hello agent, what do you hear?',
				456,
				[],
			);
		$this->rebuildListener($agentTalkService, $sessionMapper);

		$task = $this->makeAudioTask(
			['input' => 123, 'confirmation' => 0, 'conversation_token' => ''],
			[
				'output' => 456,
				'output_transcript' => 'The agent answer',
				'input_transcript' => 'Hello agent, what do you hear?',
			],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	public function testAgentReplyReceivesNoTranscriptOnConfirmationFollowUp(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = new Session();
		$session->setAssignmentId(null);
		$session->setRequesterUserId('alice');
		$this->givenAgentSession($sessionMapper, $session);

		$this->givenUnstoredMessage();

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				'The agent answer',
				null,
				null,
				456,
				[],
			);
		$this->rebuildListener($agentTalkService, $sessionMapper);

		$task = $this->makeAudioTask(
			['input' => 123, 'confirmation' => 1, 'conversation_token' => 'token'],
			[
				'output' => 456,
				'output_transcript' => 'The agent answer',
				'input_transcript' => 'Hello agent, what do you hear?',
			],
		);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}

	public function testAgentReplyReceivesTheMultimodalOutputFiles(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$session = new Session();
		$session->setAssignmentId(null);
		$session->setRequesterUserId('alice');
		$this->givenAgentSession($sessionMapper, $session);

		$this->givenUnstoredMessage();

		/** @var AgentTalkService&MockObject $agentTalkService */
		$agentTalkService = $this->createMock(AgentTalkService::class);
		$agentTalkService->expects($this->once())
			->method('postAgentTalkReply')
			->with(
				$session,
				'Here is your image.',
				null,
				null,
				null,
				[789, 790],
			);
		$this->rebuildListener($agentTalkService, $sessionMapper);

		$task = new Task(
			MultimodalContextAgentInteraction::ID,
			[
				'input' => 'Generate an image of a cat',
				'confirmation' => 0,
				'conversation_token' => '',
				'input_attachments' => [],
			],
			'assistant:chatty-llm',
			'admin',
			'chatty-llm:' . self::SESSION_ID,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput([
			'output' => 'Here is your image.',
			'output_attachments' => [789, 790],
		]);
		$this->listener->handle(new TaskSuccessfulEvent($task));
	}
}
