<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\NotificationService;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\TaskProcessing\Task;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase {

	private const TASK_USER = 'notify-test-user';

	/**
	 * Get the service wired to a notification manager mock whose created notification
	 * records everything the service sets on it into the given array
	 *
	 * @param array<string, mixed> $captured filled by reference with the notification fields
	 * @return NotificationService
	 */
	private function getService(array &$captured): NotificationService {
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnCallback(static function (string $app) use ($notification, &$captured): INotification {
			$captured['app'] = $app;
			return $notification;
		});
		$notification->method('setUser')->willReturnCallback(static function (string $user) use ($notification, &$captured): INotification {
			$captured['user'] = $user;
			return $notification;
		});
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnCallback(static function (string $type, string $id) use ($notification, &$captured): INotification {
			$captured['objectType'] = $type;
			$captured['objectId'] = $id;
			return $notification;
		});
		$notification->method('setSubject')->willReturnCallback(static function (string $subject, array $parameters) use ($notification, &$captured): INotification {
			$captured['subject'] = $subject;
			$captured['subjectParameters'] = $parameters;
			return $notification;
		});

		$manager = $this->createMock(IManager::class);
		$manager->method('createNotification')->willReturn($notification);
		$manager->expects($this->once())
			->method('notify')
			->with($this->identicalTo($notification));

		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturnCallback(static function (string $route, array $args): string {
			return '/absolute/' . $route . '?' . http_build_query($args);
		});

		return new NotificationService($manager, $url);
	}

	private function getTask(string $appId, ?string $customId, int $status): Task {
		$task = new Task('core:text2text', ['input' => 'Write a haiku'], $appId, self::TASK_USER, $customId);
		$task->setId(204);
		$task->setStatus($status);
		return $task;
	}

	public function testChatMessageSuccessNotification(): void {
		$captured = [];
		$service = $this->getService($captured);

		$service->sendNotification($this->getTask(Application::APP_ID . ':chatty-llm', 'chatty-llm:42', Task::STATUS_SUCCESSFUL));

		$this->assertSame(Application::APP_ID, $captured['app']);
		$this->assertSame(self::TASK_USER, $captured['user']);
		$this->assertSame('chat_success', $captured['subject']);
		// "chat", not "task": the frontend action interceptor must NOT cancel
		// the WEB navigation for chat conversations
		$this->assertSame('chat', $captured['objectType']);
		$this->assertSame('204', $captured['objectId']);
		$params = $captured['subjectParameters'];
		$this->assertSame('42', $params['sessionId']);
		// no prepared URL: the Notifier generates the link when rendering
		$this->assertNull($params['target']);
		$this->assertSame(Application::APP_ID . ':chatty-llm', $params['appId']);
		$this->assertSame(204, $params['id']);
		$this->assertSame(['input' => 'Write a haiku'], $params['inputs']);
	}

	public function testChatMessageFailureNotification(): void {
		$captured = [];
		$service = $this->getService($captured);

		$service->sendNotification($this->getTask(Application::APP_ID . ':chatty-llm', 'chatty-llm:42', Task::STATUS_FAILED));

		$this->assertSame('chat_failure', $captured['subject']);
		$params = $captured['subjectParameters'];
		$this->assertSame('42', $params['sessionId']);
		// no prepared URL: the Notifier generates the link when rendering
		$this->assertNull($params['target']);
	}

	public function testChatMessageRegenerationNotification(): void {
		$captured = [];
		$service = $this->getService($captured);

		// regeneration customIds append the query message id to the session id
		$service->sendNotification($this->getTask(Application::APP_ID . ':chatty-llm', 'chatty-llm:42:7', Task::STATUS_SUCCESSFUL));

		$this->assertSame('chat_success', $captured['subject']);
		$this->assertSame('42', $captured['subjectParameters']['sessionId']);
	}

	public function testNonChatTaskSuccessNotification(): void {
		$captured = [];
		$service = $this->getService($captured);

		$service->sendNotification($this->getTask(Application::APP_ID, 'some-custom-id', Task::STATUS_SUCCESSFUL));

		$this->assertSame('success', $captured['subject']);
		$this->assertSame('task', $captured['objectType']);
		$params = $captured['subjectParameters'];
		$this->assertArrayNotHasKey('sessionId', $params);
		$this->assertNull($params['target']);
	}

	public function testNonChatTaskFailureNotification(): void {
		$captured = [];
		$service = $this->getService($captured);

		$service->sendNotification($this->getTask(Application::APP_ID, 'some-custom-id', Task::STATUS_FAILED));

		$this->assertSame('failure', $captured['subject']);
		$this->assertArrayNotHasKey('sessionId', $captured['subjectParameters']);
		$this->assertNull($captured['subjectParameters']['target']);
	}

	public function testChatTitleTaskKeepsDefaultNotification(): void {
		$captured = [];
		$service = $this->getService($captured);

		// title generation tasks share the chat appId but must not be treated as chat messages
		$service->sendNotification($this->getTask(Application::APP_ID . ':chatty-llm', 'chatty-title:42', Task::STATUS_SUCCESSFUL));

		$this->assertSame('success', $captured['subject']);
		$this->assertArrayNotHasKey('sessionId', $captured['subjectParameters']);
		$this->assertNull($captured['subjectParameters']['target']);
	}
}
