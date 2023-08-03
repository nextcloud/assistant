<?php

namespace OCA\TPAssistant\Notification;

use InvalidArgumentException;
use OCA\TPAssistant\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;

use OCP\Notification\INotifier;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $factory,
		private IUserManager $userManager,
		private IURLGenerator $url,
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
		$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));

		switch ($notification->getSubject()) {
			case 'success':
				$subject = $l->t('Assistant Task for app %1$s has finished', [$params['appId']]);
				$content = $l->t('The input was: %1$s', [$params['input']]);
				$link = $params['target'] ?? $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getTaskResultPage', ['taskId' => $params['id']]);

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
				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new InvalidArgumentException();
		}
	}
}
