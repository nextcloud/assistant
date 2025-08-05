<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Notification;

use InvalidArgumentException;
use OCA\Assistant\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;

use OCP\Notification\INotifier;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToText;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $factory,
		private IURLGenerator $url,
		private IAppManager $appManager,
		private ITaskProcessingManager $taskProcessingManager,
		private LoggerInterface $logger,
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

		if (in_array($notification->getSubject(), ['success', 'failure'], true)) {
			// ignore old notifications (before meta tasks were introduced)
			if (!isset($params['target'], $params['inputs'])) {
				throw new InvalidArgumentException();
			}
			$schedulingAppId = $params['appId'];
			$schedulingAppInfo = $this->appManager->getAppInfo($schedulingAppId);
			if ($schedulingAppInfo === null) {
				throw new InvalidArgumentException();
			}
			$schedulingAppName = $schedulingAppInfo['name'];

			$taskTypeName = null;
			$taskInput = $params['inputs']['input'] ?? null;

			try {
				if (!isset($params['taskTypeId'])) {
					$taskTypeName = $l->t('Assistant task');
				} elseif ($params['taskTypeId'] === TextToText::ID) {
					$taskTypeName = $l->t('AI text generation');
				} elseif ($params['taskTypeId'] === TextToImage::ID) {
					$taskTypeName = $l->t('AI image generation');
				} elseif ($params['taskTypeId'] === AudioToText::ID) {
					$taskTypeName = $l->t('AI audio transcription');
				} elseif ($params['taskTypeId'] === 'copywriter') {
					// TODO adjust that when we have copywriter back on its feet
					// Catch the custom copywriter task type built on top of the FreePrompt task type.
					$taskTypeName = $l->t('AI context writer');
					$taskInput = $l->t('Writing style: %1$s; Source material: %2$s', [$params['inputs']['writingStyle'], $params['inputs']['sourceMaterial']]);
				} elseif (
					$params['taskTypeId'] === 'context_chat:context_chat'
					|| $params['taskTypeId'] === 'legacy:TextProcessing:OCA\ContextChat\TextProcessing\ContextChatTaskType'
				) {
					$taskInput = $params['inputs']['prompt'] ?? null;
					$taskTypeName = $l->t('Context Chat');
				} else {
					$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
					if (isset($availableTaskTypes[$params['taskTypeId']])) {
						$taskType = $availableTaskTypes[$params['taskTypeId']];
						$taskTypeName = $taskType['name'];
					}
				}
			} catch (\Exception|\Throwable $e) {
				$this->logger->debug('Impossible to get task type ' . $params['taskTypeId'], ['exception' => $e]);
			}
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

				$link = $params['target'];
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
				if ($taskInput) {
					$content .= $l->t('Input: %1$s', [$taskInput]);
				}


				$link = $params['target'] ?? $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getAssistantTaskResultPage', ['taskId' => $params['id']]);
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

			case 'file_action_success':
				$subject = $l->t('File action has finished');

				$sourceFileLink = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $params['source_file_id']]);
				$targetFileLink = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $params['target_file_id']]);
				$taskLink = $params['target'];
				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));

				switch ($params['task_type_id']) {
					case TextToTextSummary::ID:
						$message = $l->t('{sourceFile} has been summarized in {targetFile}');
						break;
					case AudioToText::ID:
						$message = $l->t('{sourceFile} has been transcribed in {targetFile}');
						break;
					case class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech') ? \OCP\TaskProcessing\TaskTypes\TextToSpeech::ID : 'nope':
						$message = $l->t('{sourceFile} has been converted to audio in {targetFile}');
						break;
					default:
						$message = $l->t('{sourceFile} has been processed, {targetFile} was created');
				}

				$notification
					->setParsedSubject($subject)
					->setRichMessage($message, [
						'sourceFile' => [
							'type' => 'file',
							'id' => (string)$params['source_file_id'],
							'name' => $params['source_file_name'],
							'path' => $params['source_file_path'],
							'link' => $sourceFileLink,
						],
						'targetFile' => [
							'type' => 'file',
							'id' => (string)$params['target_file_id'],
							'name' => $params['target_file_name'],
							'path' => $params['target_file_path'],
							'link' => $targetFileLink,
						],
					])
					->setLink($taskLink)
					->setIcon($iconUrl);

				$actionLabel = $l->t('View results');
				$action = $notification->createAction();
				$action->setLabel($actionLabel)
					->setParsedLabel($actionLabel)
					->setLink($taskLink, IAction::TYPE_WEB)
					->setPrimary(true);

				$notification->addParsedAction($action);

				return $notification;

			case 'file_action_failure':
				$subject = $l->t('File action has failed');

				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));

				switch ($params['task_type_id']) {
					case TextToTextSummary::ID:
						$message = $l->t('Summarization of {sourceFile} has failed');
						break;
					case AudioToText::ID:
						$message = $l->t('Transcription of {sourceFile} has failed');
						break;
					case class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech') ? \OCP\TaskProcessing\TaskTypes\TextToSpeech::ID : 'nope':
						$message = $l->t('The text-to-speech process for {sourceFile} has failed');
						break;
					default:
						$message = $l->t('The processing of {sourceFile} has failed');
				}

				$notification
					->setParsedSubject($subject)
					->setRichMessage($message, [
						'sourceFile' => [
							'type' => 'file',
							'id' => (string)$params['source_file_id'],
							'name' => $params['source_file_name'],
							'path' => $params['source_file_path'],
							'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $params['source_file_id']]),
						],
					])
					->setIcon($iconUrl);

				return $notification;

			case 'new_image_file_success':
				$subject = $l->t('New image file has been generated');

				$targetDirLink = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $params['target_directory_id']]);
				$targetFileLink = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $params['target_file_id']]);
				$taskLink = $params['target'];
				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));

				$message = $l->t('{targetFile} has been generated in {targetDirectory}');

				$notification
					->setParsedSubject($subject)
					->setRichMessage($message, [
						'targetDirectory' => [
							'type' => 'file',
							'id' => (string)$params['target_directory_id'],
							'name' => $params['target_directory_name'],
							'path' => $params['target_directory_path'],
							'link' => $targetDirLink,
						],
						'targetFile' => [
							'type' => 'file',
							'id' => (string)$params['target_file_id'],
							'name' => $params['target_file_name'],
							'path' => $params['target_file_path'],
							'link' => $targetFileLink,
						],
					])
					->setLink($taskLink)
					->setIcon($iconUrl);

				$actionLabel = $l->t('View results');
				$action = $notification->createAction();
				$action->setLabel($actionLabel)
					->setParsedLabel($actionLabel)
					->setLink($taskLink, IAction::TYPE_WEB)
					->setPrimary(true);

				$notification->addParsedAction($action);

				return $notification;

			case 'new_image_file_failure':
				$subject = $l->t('Image file generation has failed');

				$iconUrl = $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));

				$message = $l->t('Generation of a new image file in {targetDirectory} has failed');

				$notification
					->setParsedSubject($subject)
					->setRichMessage($message, [
						'targetDirectory' => [
							'type' => 'file',
							'id' => (string)$params['target_directory_id'],
							'name' => $params['target_directory_name'],
							'path' => $params['target_directory_path'],
							'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $params['target_directory_id']]),
						],
					])
					->setIcon($iconUrl);

				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new InvalidArgumentException();
		}
	}
}
