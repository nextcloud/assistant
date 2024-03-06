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
use OCA\TpAssistant\Service\SpeechToText\SpeechToTextService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SpeechToTextApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private SpeechToTextService $service,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $id Transcript ID
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getTranscript(int $id): DataResponse {
		if ($this->userId === null) {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->service->internalGetTask($this->userId, $id)->getOutput());
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), intval($e->getCode()));
		}
	}


	/**
	 * @return DataResponse
	 * @throws NotPermittedException
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
