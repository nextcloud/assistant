<?php

namespace OCA\TPAssistant\Notification;

use InvalidArgumentException;
use OCA\TPAssistant\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;

use OCP\Notification\INotifier;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $factory,
		private IURLGenerator $url,
		private IAppManager $appManager,
		private ?string $userId,
	) {
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return Application::APP_ID;
	}
	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get(Application::APP_ID)->t('Nextcloud assistant');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			// Not my app => throw
			throw new InvalidArgumentException();
		}

		$l = $this->factory->get(Application::APP_ID, $languageCode);

		$params = $notification->getSubjectParameters();
		$schedulingAppId = $params['appId'];
		$schedulingAppInfo = $this->appManager->getAppInfo($schedulingAppId);
		if ($schedulingAppInfo === null) {
			throw new InvalidArgumentException();
		}
		$schedulingAppName = $schedulingAppInfo['name'];

		switch ($notification->getSubject()) {
			case 'success':
				$subject = $l->t('Assistant Task for %1$s has finished', [$schedulingAppName]);
				$content = $l->t('The input was: %1$s', [$params['input']]);
				$link = $params['target'] ?? $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getTaskResultPage', ['taskId' => $params['id']]);
				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($content)
					->setLink($link)
					->setIcon($iconUrl);

				$actionLabel = $l->t('View results');
				$action = $notification->createAction();
				$action->setLabel($actionLabel)
					->setParsedLabel($actionLabel)
					->setLink($notification->getLink(), IAction::TYPE_WEB)
					->setPrimary(true);

				$notification->addParsedAction($action);

				return $notification;

			case 'failure':
				$subject = $l->t('Assistant Task for %1$s has failed', [$schedulingAppName]);
				$content = $l->t('The input was: %1$s', [$params['input']]);
				$link = $params['target'] ?? $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getTaskResultPage', ['taskId' => $params['id']]);
				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/error.svg'));

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($content)
					->setLink($link)
					->setIcon($iconUrl);

				$actionLabel = $l->t('View task');
				$action = $notification->createAction();
				$action->setLabel($actionLabel)
					->setParsedLabel($actionLabel)
					->setLink($notification->getLink(), IAction::TYPE_WEB)
					->setPrimary(true);

				$notification->addParsedAction($action);

				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new InvalidArgumentException();
		}
	}
}
