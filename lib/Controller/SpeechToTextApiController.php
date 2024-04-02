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

namespace OCA\Assistant\Controller;

use Exception;
use InvalidArgumentException;
use OC\User\NoUserException;
use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\SpeechToText\SpeechToTextService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @psalm-import-type AssistantTask from ResponseDefinitions
 */
class SpeechToTextApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private SpeechToTextService $sttService,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get transcription text from transcription task ID
	 *
	 * @param int $id Transcript ID
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, string, array{}>
	 */
	#[NoAdminRequired]
	public function getTranscript(int $id): DataResponse {
		if ($this->userId === null) {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->sttService->internalGetTask($this->userId, $id)->getOutput());
		} catch (Exception $e) {
			/** @var Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND $exceptionCode */
			$exceptionCode = (int) $e->getCode();
			return new DataResponse($e->getMessage(), $exceptionCode);
		}
	}


	/**
	 * Transcribe uploaded audio file
	 *
	 * Schedule audio transcription of an uploaded file and return the created task.
	 *
	 * @param string $appId App id to be set in the created task
	 * @param string $identifier Identifier to be set in the created task
	 * @return DataResponse<Http::STATUS_OK, array{task: AssistantTask}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_BAD_REQUEST, string, array{}>
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 *
	 * 200: Task started successfully
	 * 400: Starting task is not possible
	 */
	#[NoAdminRequired]
	public function transcribeAudio(string $appId, string $identifier): DataResponse {
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
			$task = $this->sttService->transcribeAudio($audioData['tmp_name'], $this->userId, $appId, $identifier);
			return new DataResponse([
				'task' => $task->jsonSerializeCc(),
			]);
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
	 * Transcribe file from user's storage
	 *
	 * Schedule audio transcription of a user's storage file and return the created task
	 *
	 * @param string $path Nextcloud file path
	 * @param string $appId App id to be set in the created task
	 * @param string $identifier Identifier to be set in the created task
	 * @return DataResponse<Http::STATUS_OK, array{task: AssistantTask}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, string, array{}>
	 * @throws InvalidPathException
	 * @throws NoUserException
	 * @throws \OCP\DB\Exception
	 *
	 * 200: Task started successfully
	 * 400: Starting task is not possible
	 * 404: File not found
	 */
	#[NoAdminRequired]
	public function transcribeFile(string $path, string $appId, string $identifier): DataResponse {
		if ($path === '') {
			return new DataResponse('Empty file path received', Http::STATUS_BAD_REQUEST);
		}

		try {
			$task = $this->sttService->transcribeFile($path, $this->userId, $appId, $identifier);
			return new DataResponse([
				'task' => $task->jsonSerializeCc(),
			]);
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
