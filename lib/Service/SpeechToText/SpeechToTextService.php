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

namespace OCA\TpAssistant\Service\SpeechToText;

use DateTime;
use Exception;
use InvalidArgumentException;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTask;
use OCA\TpAssistant\Db\MetaTaskMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use OCP\SpeechToText\ISpeechToTextManager;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SpeechToTextService {

	public function __construct(
		private ISpeechToTextManager $speechToTextManager,
		private IRootFolder $rootFolder,
		private IConfig $config,
		private MetaTaskMapper $metaTaskMapper,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Internal function to get transcription assistant tasks based on the assistant meta task id
	 *
	 * @param string $userId
	 * @param integer $metaTaskId
	 * @return MetaTask
	 * @throws Exception
	 */
	public function internalGetTask(string $userId, int $metaTaskId): MetaTask {
		try {
			$metaTask = $this->metaTaskMapper->getUserMetaTask($metaTaskId, $userId);

			if($metaTask->getCategory() !== Application::TASK_CATEGORY_SPEECH_TO_TEXT) {
				throw new Exception('Task is not a speech to text task.', Http::STATUS_BAD_REQUEST);
			}

			return $metaTask;
		} catch (MultipleObjectsReturnedException $e) {
			$this->logger->error('Multiple tasks found for one id: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			throw new Exception($this->l10n->t('Multiple tasks found'), Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			throw new Exception($this->l10n->t('Transcript not found'), Http::STATUS_NOT_FOUND);
		} catch (Exception $e) {
			$this->logger->error('Error: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			throw new Exception(
				$this->l10n->t('Some internal error occurred. Contact your sysadmin for more info.'),
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}
	}

	/**
	 * @param string $path
	 * @param string|null $userId
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws PreConditionNotMetException
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function transcribeFile(string $path, ?string $userId): void {
		// this also prevents NoUserException
		if (is_null($userId)) {
			throw new InvalidArgumentException('userId must not be null');
		}

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$audioFile = $userFolder->get($path);
		if (!$audioFile instanceof File) {
			throw new InvalidArgumentException('Cannot transcribe a non-file node');
		}

		$this->speechToTextManager->scheduleFileTranscription($audioFile, $userId, Application::APP_ID);

		$this->metaTaskMapper->createMetaTask(
			$userId,
			['fileId' => $audioFile->getId(), 'eTag' => $audioFile->getEtag()],
			'',
			time(),
			$audioFile->getId(),
			'speech-to-text',
			Application::APP_ID,
			Application::STATUS_META_TASK_SCHEDULED,
			Application::TASK_CATEGORY_SPEECH_TO_TEXT);
	}

	/**
	 * @param string $tempFileLocation
	 * @param string|null $userId
	 * @throws NotPermittedException
	 * @throws PreConditionNotMetException
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function transcribeAudio(string $tempFileLocation, ?string $userId): void {
		if ($userId === null) {
			throw new InvalidArgumentException('userId must not be null');
		}

		$audioFile = $this->getFileObject($userId, $tempFileLocation);

		$this->speechToTextManager->scheduleFileTranscription($audioFile, $userId, Application::APP_ID);

		$this->metaTaskMapper->createMetaTask(
			$userId,
			['fileId' => $audioFile->getId(), 'eTag' => $audioFile->getEtag()],
			'',
			time(),
			$audioFile->getId(),
			'speech-to-text',
			Application::APP_ID,
			Application::STATUS_META_TASK_SCHEDULED,
			Application::TASK_CATEGORY_SPEECH_TO_TEXT);
	}

	/**
	 * @param string $userId
	 * @param string $tempFileLocation
	 * @return File
	 * @throws NotPermittedException
	 * @throws RuntimeException
	 */
	private function getFileObject(string $userId, string $tempFileLocation): File {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$sttFolderName = $this->config->getAppValue(Application::APP_ID, 'stt_folder', '(not set)');
		if ($sttFolderName === '(not set)') {
			$sttFolderName = Application::SPEECH_TO_TEXT_REC_FOLDER;

			if ($userFolder->nodeExists($sttFolderName)) {
				$sttFolder = $this->getUniqueNamedFolder($userId);
				$sttFolderName = $sttFolder->getName();
			} else {
				$sttFolder = $userFolder->newFolder($sttFolderName);
			}
			$this->config->setAppValue(Application::APP_ID, 'stt_folder', $sttFolderName);
		} else {
			try {
				$sttFolder = $userFolder->get($sttFolderName);
			} catch (NotFoundException $e) {
				// it was deleted
				$sttFolder = $this->getUniqueNamedFolder($userId);
				$sttFolderName = $sttFolder->getName();
				$this->config->setAppValue(Application::APP_ID, 'stt_folder', $sttFolderName);
			}
			if (!$sttFolder instanceof Folder) {
				// the folder created by this app was tampered with
				// create a new one
				$sttFolder = $this->getUniqueNamedFolder($userId);
				$sttFolderName = $sttFolder->getName();
				$this->config->setAppValue(Application::APP_ID, 'stt_folder', $sttFolderName);
			}
		}

		$filename = (new DateTime())->format('d-M-Y-Hisu') . '.mp3';
		$audioFile = $sttFolder->newFile($filename, fopen($tempFileLocation, 'rb'));

		return $audioFile;
	}

	/**
	 * @param string $userId
	 * @param integer $try
	 * @return Folder
	 * @throws RuntimeException
	 * @throws NotPermittedException
	 */
	private function getUniqueNamedFolder(string $userId, int $try = 3): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$sttFolderPath = Application::SPEECH_TO_TEXT_REC_FOLDER . ' ' . strval(4 - $try);

		if ($userFolder->nodeExists($sttFolderPath)) {
			if ($try === 0) {
				// give up
				throw new RuntimeException('Could not create a folder with a unique name');
			}
			return $this->getUniqueNamedFolder($userId, $try - 1);
		}

		return $userFolder->newFolder($sttFolderPath);
	}
}
