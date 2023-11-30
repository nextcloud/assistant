<?php

namespace OCA\Assistant\AppInfo;

use OCA\Assistant\Listener\BeforeTemplateRenderedListener;
use OCA\Assistant\Listener\TaskFailedListener;
use OCA\Assistant\Listener\TaskSuccessfulListener;
use OCA\Assistant\Notification\Notifier;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\TextProcessing\Events\TaskFailedEvent;
use OCP\TextProcessing\Events\TaskSuccessfulEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'assistant';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);

		$context->registerEventListener(TaskSuccessfulEvent::class, TaskSuccessfulListener::class);
		$context->registerEventListener(TaskFailedEvent::class, TaskFailedListener::class);
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
	}
}

