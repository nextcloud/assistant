<?php

namespace OCA\TPAssistant\AppInfo;

use OCA\TPAssistant\Listener\TaskFailedListener;
use OCA\TPAssistant\Listener\TaskSuccessfulListener;
use OCP\IConfig;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\TextProcessing\Events\TaskFailedEvent;
use OCP\TextProcessing\Events\TaskSuccessfulEvent;
use OCP\Util;

class Application extends App implements IBootstrap {

	public const APP_ID = 'textprocessing_assistant';

	private IConfig $config;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->query(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(TaskSuccessfulEvent::class, TaskSuccessfulListener::class);
		$context->registerEventListener(TaskFailedEvent::class, TaskFailedListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (
			$userId
		) {
			Util::addScript(self::APP_ID, self::APP_ID . '-assistant');
		});
	}
}

