<?php

declare(strict_types=1);

namespace OCA\Assistant\Listener;

use OCA\Assistant\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeTemplateRenderedListener implements IEventListener {

	public function __construct(
		private IUserSession $userSession,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IInitialState $initialStateService,
		private IEventDispatcher $eventDispatcher,
		private ?string $userId,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			// Unrelated
			return;
		}

		if ($event->getResponse()->getRenderAs() !== TemplateResponse::RENDER_AS_USER) {
			return;
		}

		if (!$this->userSession->getUser() instanceof IUser) {
			return;
		}

		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());

		$adminAssistantEnabled = $this->appConfig->getValueString(Application::APP_ID, 'assistant_enabled', '1') === '1';
		$userAssistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';
		$assistantEnabled = $adminAssistantEnabled && $userAssistantEnabled;
		$this->initialStateService->provideInitialState('assistant-enabled', $assistantEnabled);
		if ($assistantEnabled) {
			$lastTargetLanguage = $this->config->getUserValue($this->userId, Application::APP_ID, 'last_target_language', '');
			$this->initialStateService->provideInitialState('last-target-language', $lastTargetLanguage);
		}
		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
	}
}
