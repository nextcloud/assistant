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
use OCA\TpAssistant\Service\SpeechToText\SpeechToTextService;
use OCA\TpAssistant\Service\AssistantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\SpeechToText\Events\AbstractTranscriptionEvent;
use OCP\SpeechToText\Events\TranscriptionFailedEvent;
use OCP\SpeechToText\Events\TranscriptionSuccessfulEvent;
use OCA\TpAssistant\Db\TaskMapper;
use Psr\Log\LoggerInterface;
use OCP\IURLGenerator;

/**
 * @template-implements IEventListener<Event>
 */
class SpeechToTextResultListener implements IEventListener {
	public function __construct(
		private SpeechToTextService $sttService,
		private LoggerInterface $logger,
		private TaskMapper $taskMapper,
		private AssistantService $assistantService,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AbstractTranscriptionEvent || $event->getAppId() !== Application::APP_ID) {
			return;
		}

		if ($event instanceof TranscriptionSuccessfulEvent) {
			$transcript = $event->getTranscript();
			$userId = $event->getUserId();
			$file = $event->getFile();

			$tasks = $this->taskMapper->getTasksByOcpTaskIdAndModality($file->getId(), Application::TASK_TYPE_SPEECH_TO_TEXT);

			// Find a matching etag:
			$etag = $file->getEtag();
			$assistantTask = null;
			foreach ($tasks as $task) {
				$taskEtag = $task->getInputsAsArray()['eTag'];
				if ($taskEtag === $etag) {
					$assistantTask = $task;
					break;
				}
			}

			if ($assistantTask === null) {
				$this->logger->error('No assistant task found for speech to text result out of ' . count($tasks) . ' tasks for file ' . $file->getId() . ' with etag ' . $etag);
				return;
			}

			// Update the meta task with the output and new status
			$assistantTask->setOutput($transcript);
			$assistantTask->setStatus(Application::STT_TASK_SUCCESSFUL);
			$assistantTask = $this->taskMapper->update($assistantTask);

			// Generate the link to the result page:
			$link = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.SpeechToText.getResultPage', ['id' => $task->getId()]);
			$this->logger->error('Generated link to result page: ' . $link);
			try {
				$this->assistantService->sendNotification($assistantTask, $link, null, $transcript);
			} catch (\InvalidArgumentException $e) {
				$this->logger->error('Failed to dispatch notification for successful transcription: ' . $e->getMessage());
			}
		}

		if ($event instanceof TranscriptionFailedEvent) {
			$this->logger->error('Transcript generation failed: ' . $event->getErrorMessage());

			$userId = $event->getUserId();
			$tasks = $this->taskMapper->getTasksByOcpTaskIdAndModality($file->getId(), Application::TASK_TYPE_SPEECH_TO_TEXT);

			// Find a matching etag:
			$etag = $file->getEtag();
			$assistantTask = null;
			foreach ($tasks as $task) {
				if ($task->getEtag() === $etag) {
					$assistantTask = $task;
					break;
				}
			}

			if ($assistantTask === null) {
				$this->logger->error('No assistant task found for speech to text result');
				return;
			}

			// Update the meta task with the new status
			$assistantTask->setStatus(Application::STT_TASK_FAILED);
			$assistantTask = $this->taskMapper->update($assistantTask);
			
			try {
				$this->assistantService->sendNotification($assistantTask);
			} catch (\InvalidArgumentException $e) {
				$this->logger->error('Failed to dispatch notification for failed transcription: ' . $e->getMessage());
			}
			
		}
	}
}
