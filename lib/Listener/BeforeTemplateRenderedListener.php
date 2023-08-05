<?php

declare(strict_types=1);

namespace OCA\TPAssistant\Listener;

use OCA\TPAssistant\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

class BeforeTemplateRenderedListener implements IEventListener {

	public function __construct(
		private IUserSession $userSession,
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

		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
	}
}
