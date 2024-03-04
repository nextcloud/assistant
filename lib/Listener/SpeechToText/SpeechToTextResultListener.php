<?php
/**
 * @copyright Copyright (c) 2023 Anupam Kumar <kyteinsky@gmail.com>
 *
 * @author Anupam Kumar <kyteinsky@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\TpAssistant\Listener\SpeechToText;

use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTaskMapper;
use OCA\TpAssistant\Service\NotificationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\SpeechToText\Events\AbstractTranscriptionEvent;
use OCP\SpeechToText\Events\TranscriptionFailedEvent;
use OCP\SpeechToText\Events\TranscriptionSuccessfulEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class SpeechToTextResultListener implements IEventListener {
	public function __construct(
		private LoggerInterface  $logger,
		private MetaTaskMapper $metaTaskMapper,
		private NotificationService $notificationService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AbstractTranscriptionEvent || $event->getAppId() !== Application::APP_ID) {
			return;
		}

		if ($event instanceof TranscriptionSuccessfulEvent) {
			$transcript = $event->getTranscript();
			$file = $event->getFile();

			$metaTasks = $this->metaTaskMapper->getMetaTasksByOcpTaskIdAndCategory($file->getId(), Application::TASK_CATEGORY_SPEECH_TO_TEXT);

			// Find a matching etag:
			$etag = $file->getEtag();
			$assistantTask = null;
			foreach ($metaTasks as $metaTask) {
				$metaTaskEtag = $metaTask->getInputsAsArray()['eTag'];
				if ($metaTaskEtag === $etag) {
					$assistantTask = $metaTask;
					break;
				}
			}

			if ($assistantTask === null) {
				$this->logger->error('No assistant task found for speech to text result out of ' . count($metaTasks) . ' tasks for file ' . $file->getId() . ' with etag ' . $etag);
				return;
			}

			// Update the meta task with the output and new status
			$assistantTask->setOutput($transcript);
			$assistantTask->setStatus(Application::STATUS_META_TASK_SUCCESSFUL);
			$assistantTask = $this->metaTaskMapper->update($assistantTask);

			try {
				$this->notificationService->sendNotification($assistantTask, null, null, $transcript);
			} catch (\InvalidArgumentException $e) {
				$this->logger->error('Failed to dispatch notification for successful transcription: ' . $e->getMessage());
			}
		}

		if ($event instanceof TranscriptionFailedEvent) {
			$file = $event->getFile();
			$this->logger->error('Transcript generation failed: ' . $event->getErrorMessage());

			$metaTasks = $this->metaTaskMapper->getMetaTasksByOcpTaskIdAndCategory($file->getId(), Application::TASK_CATEGORY_SPEECH_TO_TEXT);

			// Find a matching etag:
			$etag = $file->getEtag();
			$assistantTask = null;
			foreach ($metaTasks as $metaTask) {
				$metaTaskEtag = $metaTask->getInputsAsArray()['eTag'];
				if ($metaTaskEtag === $etag) {
					$assistantTask = $metaTask;
					break;
				}
			}

			if ($assistantTask === null) {
				$this->logger->error('No assistant task found for speech to text result (task id: ' . $file->getId() . ')');
				return;
			}

			// Update the meta task with the new status
			$assistantTask->setStatus(Application::STATUS_META_TASK_FAILED);
			$assistantTask->setOutput($event->getErrorMessage());
			$assistantTask = $this->metaTaskMapper->update($assistantTask);

			try {
				$this->notificationService->sendNotification($assistantTask);
			} catch (\InvalidArgumentException $e) {
				$this->logger->error('Failed to dispatch notification for failed transcription: ' . $e->getMessage());
			}

		}
	}
}
