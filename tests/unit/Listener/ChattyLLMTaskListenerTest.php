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
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\TaskProcessing\Task;
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
		$this->sessionMapper->method('getUserSession')->willReturn($session);

		$this->listener = new ChattyLLMTaskListener(
			$this->messageMapper,
			$this->sessionMapper,
			$this->createMock(TaskProcessingService::class),
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
		$this->assertSame('assistant', $storedMessage->getRole());
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
}
