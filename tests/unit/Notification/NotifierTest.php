<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Notification;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Notification\Notifier;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotifierTest extends TestCase {

	private const TARGET_URL = 'https://cloud.example/apps/assistant?sessionId=42';

	/** @var array<string, mixed> everything the notifier sets on the notification mock */
	private array $recorded = [];

	private function getNotifier(): Notifier {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			return $parameters === [] ? $text : vsprintf($text, $parameters);
		});

		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l);

		$url = $this->createMock(IURLGenerator::class);
		$url->method('imagePath')->willReturnCallback(static function (string $app, string $file): string {
			return 'apps/' . $app . '/' . $file;
		});
		$url->method('getAbsoluteURL')->willReturnCallback(static function (string $path): string {
			return 'https://cloud.example/' . $path;
		});
		$url->method('linkToRouteAbsolute')->willReturnCallback(static function (string $route, array $args): string {
			return '/route/' . $route . '?' . http_build_query($args);
		});

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppInfo')->willReturnCallback(static function (string $appId): ?array {
			return $appId === Application::APP_ID ? ['id' => $appId, 'name' => 'Assistant'] : null;
		});

		return new Notifier(
			$factory,
			$url,
			$appManager,
			$this->createMock(ITaskProcessingManager::class),
			$this->createMock(LoggerInterface::class),
		);
	}

	private function getNotification(string $subject, array $params, string $app = Application::APP_ID): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn($app);
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getSubjectParameters')->willReturn($params);
		$notification->method('setParsedSubject')->willReturnCallback(function (string $parsedSubject) use ($notification): INotification {
			$this->recorded['parsedSubject'] = $parsedSubject;
			return $notification;
		});
		$notification->method('setParsedMessage')->willReturnCallback(function (string $message) use ($notification): INotification {
			$this->recorded['parsedMessage'] = $message;
			return $notification;
		});
		$notification->method('setLink')->willReturnCallback(function (string $link) use ($notification): INotification {
			$this->recorded['link'] = $link;
			return $notification;
		});
		$notification->method('setIcon')->willReturnCallback(function (string $icon) use ($notification): INotification {
			$this->recorded['icon'] = $icon;
			return $notification;
		});
		$notification->method('getLink')->willReturnCallback(function (): string {
			return $this->recorded['link'] ?? '';
		});
		$notification->method('setIcon')->willReturnCallback(function (string $icon) use ($notification): INotification {
			$this->recorded['icon'] = $icon;
			return $notification;
		});

		$action = $this->createMock(IAction::class);
		$action->method('setLabel')->willReturnSelf();
		$action->method('setParsedLabel')->willReturnCallback(function (string $label) use ($action): IAction {
			$this->recorded['actionLabel'] = $label;
			return $action;
		});
		$action->method('setLink')->willReturnSelf();
		$action->method('setPrimary')->willReturnSelf();
		$notification->method('createAction')->willReturn($action);
		$notification->method('addParsedAction')->willReturnCallback(function (IAction $parsedAction) use ($notification): INotification {
			$this->recorded['actions'][] = $parsedAction;
			return $notification;
		});

		return $notification;
	}

	public function testPrepareChatSuccess(): void {
		$notifier = $this->getNotifier();
		$notification = $this->getNotification('chat_success', [
			'sessionId' => '42',
			'target' => self::TARGET_URL,
		]);

		$prepared = $notifier->prepare($notification, 'en');

		$this->assertSame($notification, $prepared);
		$this->assertSame('Your assistant reply is ready', $this->recorded['parsedSubject']);
		$this->assertSame(self::TARGET_URL, $this->recorded['link']);
		$this->assertSame('https://cloud.example/apps/assistant/app-dark.svg', $this->recorded['icon']);
		$this->assertSame('View reply', $this->recorded['actionLabel']);
		$this->assertCount(1, $this->recorded['actions']);
	}

	public function testPrepareChatSuccessWithoutTarget(): void {
		$notifier = $this->getNotifier();
		$notification = $this->getNotification('chat_success', [
			'sessionId' => '42',
		]);

		$prepared = $notifier->prepare($notification, 'en');

		$this->assertSame($notification, $prepared);
		$this->assertSame('/route/assistant.assistant.getAssistantStandalonePage?sessionId=42', $this->recorded['link']);
	}

	public function testPrepareChatFailure(): void {
		$notifier = $this->getNotifier();
		$notification = $this->getNotification('chat_failure', [
			'sessionId' => '42',
			'target' => self::TARGET_URL,
		]);

		$prepared = $notifier->prepare($notification, 'en');

		$this->assertSame($notification, $prepared);
		$this->assertSame('Your assistant reply could not be generated', $this->recorded['parsedSubject']);
		$this->assertSame(self::TARGET_URL, $this->recorded['link']);
		$this->assertSame('https://cloud.example/apps/core/actions/error.svg', $this->recorded['icon']);
		$this->assertSame('View chat', $this->recorded['actionLabel']);
	}

	public function testPrepareTaskSuccessWithoutTarget(): void {
		$notifier = $this->getNotifier();
		$notification = $this->getNotification('success', [
			'appId' => Application::APP_ID,
			'id' => 204,
			'inputs' => ['input' => 'Write a haiku'],
			// no target: the link must be generated at render time
		]);

		$prepared = $notifier->prepare($notification, 'en');

		$this->assertSame($notification, $prepared);
		$this->assertSame('/route/assistant.assistant.getAssistantTaskResultPage?taskId=204', $this->recorded['link']);
		$this->assertStringContainsString('has finished', $this->recorded['parsedSubject']);
	}

	public function testPrepareForeignAppThrows(): void {
		$this->expectException(UnknownNotificationException::class);
		$notifier = $this->getNotifier();
		$notification = $this->getNotification('chat_success', ['sessionId' => '42'], 'some_other_app');

		$notifier->prepare($notification, 'en');
	}
}
