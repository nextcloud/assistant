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

namespace OCA\TpAssistant\Controller;

use Exception;
use InvalidArgumentException;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTask;
use OCA\TpAssistant\Db\MetaTaskMapper;
use OCA\TpAssistant\Service\SpeechToText\SpeechToTextService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SpeechToTextController extends Controller {

	public function __construct(
		string                      $appName,
		IRequest                    $request,
		private SpeechToTextService $service,
		private LoggerInterface     $logger,
		private IL10N               $l10n,
		private IInitialState       $initialState,
		private ?string             $userId,
		private MetaTaskMapper      $metaTaskMapper,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $id
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getResultPage(int $id): TemplateResponse {
		$response = new TemplateResponse(Application::APP_ID, 'speechToTextResultPage');
		try {
			$initData = [
				'task' => $this->internalGetTask($id),
			];
		} catch (Exception $e) {
			$initData = [
				'status' => 'failure',
				'task' => null,
				'message' => $e->getMessage(),
			];
			$response->setStatus(intval($e->getCode()));
		}
		$this->initialState->provideInitialState('plain-text-result', $initData);
		return $response;
	}

	/**
	 * @param int $id Transcript ID
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getTranscript(int $id): DataResponse {
		try {
			return new DataResponse($this->internalGetTask($id)->getOutput());
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), intval($e->getCode()));
		}
	}

	/**
	 * Internal function to get transcription assistant tasks based on the assistant meta task id
	 *
	 * @param integer $id
	 * @return MetaTask
	 */
	private function internalGetTask(int $id): MetaTask {
		try {
			$metaTask = $this->metaTaskMapper->getMetaTaskOfUser($id, $this->userId);

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
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function transcribeAudio(): DataResponse {
		$audioData = $this->request->getUploadedFile('audioData');

		if ($audioData['error'] !== 0) {
			return new DataResponse('Error in audio file upload: ' . $audioData['error'], Http::STATUS_BAD_REQUEST);
		}

		if (empty($audioData)) {
			return new DataResponse('Invalid audio data received', Http::STATUS_BAD_REQUEST);
		}

		if ($audioData['type'] !== 'audio/mp3' && $audioData['type'] !== 'audio/mpeg') {
			return new DataResponse('Audio file must be in MP3 format', Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->service->transcribeAudio($audioData['tmp_name'], $this->userId);
			return new DataResponse('ok');
		} catch (RuntimeException $e) {
			$this->logger->error(
				'Runtime exception: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
			return new DataResponse(
				$this->l10n->t('Some internal error occurred. Contact your sysadmin for more info.'),
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		} catch (PreConditionNotMetException $e) {
			$this->logger->error('No Speech-to-Text provider found: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(
				$this->l10n->t('No Speech-to-Text provider found, install one from the app store to use this feature.'),
				Http::STATUS_BAD_REQUEST
			);
		} catch (InvalidArgumentException $e) {
			$this->logger->error('InvalidArgumentException: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(
				$this->l10n->t('Some internal error occurred. Contact your sysadmin for more info.'),
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	/**
	 * @param string $path Nextcloud file path
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function transcribeFile(string $path): DataResponse {
		if ($path === '') {
			return new DataResponse('Empty file path received', Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->service->transcribeFile($path, $this->userId);
			return new DataResponse('ok');
		} catch (NotFoundException $e) {
			$this->logger->error('Audio file not found: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(
				$this->l10n->t('Audio file not found.'),
				Http::STATUS_NOT_FOUND
			);
		} catch (RuntimeException $e) {
			$this->logger->error(
				'Runtime exception: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
			return new DataResponse(
				$this->l10n->t('Some internal error occurred. Contact your sysadmin for more info.'),
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		} catch (NotPermittedException $e) {
			$this->logger->error(
				'No permission to create recording file/directory: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
			return new DataResponse(
				$this->l10n->t('No permission to create recording file/directory, contact your sysadmin to resolve this issue.'),
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		} catch (PreConditionNotMetException $e) {
			$this->logger->error('No Speech-to-Text provider found: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(
				$this->l10n->t('No Speech-to-Text provider found, install one from the app store to use this feature.'),
				Http::STATUS_BAD_REQUEST
			);
		} catch (InvalidArgumentException $e) {
			$this->logger->error('InvalidArgumentException: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return new DataResponse(
				$this->l10n->t('Some internal error occurred. Contact your sysadmin for more info.'),
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}
}
