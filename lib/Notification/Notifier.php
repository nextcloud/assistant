<?php

namespace OCA\TpAssistant\Notification;

use InvalidArgumentException;
use OCA\TpAssistant\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;

use OCP\Notification\INotifier;
use OCP\TextProcessing\ITaskType;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $factory,
		private IURLGenerator $url,
		private IAppManager $appManager,
		private ContainerInterface $container,
		private LoggerInterface $logger,
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
		return $this->factory->get(Application::APP_ID)->t('Nextcloud Assistant');
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

		$taskTypeName = null;
		$taskInput = $params['inputs']['prompt'] ?? null;
		if ($params['taskCategory'] === Application::TASK_CATEGORY_TEXT_GEN) {

			if ($params['taskTypeClass'] === 'copywriter') {
				// Catch the custom copywriter task type built on top of the FreePrompt task type.
				$taskTypeName = $l->t('Copywriting');
				$taskInput = $l->t('Writing style: %1$s; Source material: %2$s', [$params['inputs']['writingStyle'], $params['inputs']['sourceMaterial']]);
			} else {
				try {
					/** @var ITaskType $taskType */
					$taskType = $this->container->get($params['taskTypeClass']);
					$taskTypeName = $taskType->getName();
				} catch (\Exception | \Throwable $e) {
					$this->logger->debug('Impossible to get task type ' . $params['taskTypeClass'], ['exception' => $e]);
				}
			}
		} elseif ($params['taskCategory'] === Application::TASK_CATEGORY_TEXT_TO_IMAGE) {
			$taskTypeName = $l->t('Text to image');
		} elseif ($params['taskCategory'] === Application::TASK_CATEGORY_SPEECH_TO_TEXT) {
			$taskTypeName = $l->t('Speech to text');
		}

		switch ($notification->getSubject()) {
			case 'success':
				$subject = $taskTypeName === null
					? $l->t('Task for "%1$s" has finished', [$schedulingAppName])
					: $l->t('"%1$s" task for "%2$s" has finished', [$taskTypeName, $schedulingAppName]);

				$content = '';

				if ($taskInput) {
					$content .= $l->t('Input: %1$s', [$taskInput]);
				}
				
				if (isset($params['result'])) {
					$content === '' ?: $content .= '\n';
					$content .= $l->t('Result: %1$s', [$params['result']]);
				}
				
				$link = $params['target'] ?? $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getTextProcessingTaskResultPage', ['taskId' => $params['id']]);
				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($content)
					->setLink($link)
					->setIcon($iconUrl);

				$actionLabel = $params['actionLabel'] ?? $l->t('View results');
				$action = $notification->createAction();
				$action->setLabel($actionLabel)
					->setParsedLabel($actionLabel)
					->setLink($notification->getLink(), IAction::TYPE_WEB)
					->setPrimary(true);

				$notification->addParsedAction($action);

				return $notification;

			case 'failure':
				$subject = $taskTypeName === null
					? $l->t('Task for "%1$s" has failed', [$schedulingAppName])
					: $l->t('"%1$s" task for "%2$s" has failed', [$taskTypeName, $schedulingAppName]);

				$content = '';
				if (isset($params['input'])) {
					$content .= $l->t('Input: %1$s', [$params['input']]);
				}


				$link = $params['target'] ?? $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getTextProcessingTaskResultPage', ['taskId' => $params['id']]);
				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/error.svg'));

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($content)
					->setLink($link)
					->setIcon($iconUrl);

				$actionLabel = $params['actionLabel'] ?? $l->t('View task');
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
