<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\AssistantService;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\Exception;
use OCP\Files\GenericFileException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Lock\LockedException;
use OCP\TaskProcessing\Task;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @psalm-import-type AssistantTaskProcessingTaskType from ResponseDefinitions
 * @psalm-import-type AssistantTaskProcessingTask from ResponseDefinitions
 */
class AssistantApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $l10n,
		private AssistantService $assistantService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Notify when the task has finished
	 *
	 * Does not need bruteforce protection since we respond with success anyways
	 * as we don't want to keep the front-end waiting.
	 * However, we still use rate limiting to prevent timing attacks.
	 *
	 * @param int $ocpTaskId ID of the target task
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: Ready notification enabled successfully
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['assistant_api'])]
	public function notifyWhenReady(int $ocpTaskId): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to notify when ready; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$this->assistantService->notifyWhenReady($ocpTaskId, $this->userId);
		} catch (Exception $e) {
			// Ignore
		}
		return new DataResponse('', Http::STATUS_OK);
	}

	/**
	 * Get available task types
	 *
	 * Get all available task types that the assistant can handle.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{types: list<AssistantTaskProcessingTaskType>}, array{}>
	 *
	 * 200: Available task types returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['task_management'])]
	public function getAvailableTaskTypes(): DataResponse {
		$taskTypes = $this->assistantService->getAvailableTaskTypes();
		return new DataResponse(['types' => $taskTypes]);
	}

	/**
	 * Get user's tasks
	 *
	 * Get a list of assistant tasks for the current user.
	 *
	 * @param string|null $taskTypeId Task type id. If null, tasks of all task types will be retrieved
	 * @return DataResponse<Http::STATUS_OK, array{tasks: list<AssistantTaskProcessingTask>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
	 *
	 * 200: User tasks returned
	 * 404: No tasks found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['task_management'])]
	public function getUserTasks(?string $taskTypeId = null): DataResponse {
		if ($this->userId !== null) {
			try {
				$tasks = $this->assistantService->getUserTasks($this->userId, $taskTypeId);
				$serializedTasks = array_map(static function (Task $task) {
					return $task->jsonSerialize();
				}, $tasks);
				return new DataResponse(['tasks' => $serializedTasks]);
			} catch (Exception $e) {
				return new DataResponse(['tasks' => []]);
			}
		}
		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}

	/**
	 * Extract text from file
	 *
	 * Parse and extract text content of a file (if the file type is supported)
	 *
	 * @param string|null $filePath Path of the file to parse in the user's storage
	 * @param int|null $fileId Id of the file to parse in the user's storage
	 * @return DataResponse<Http::STATUS_OK, array{parsedText: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: Text parsed from file successfully
	 * 400: Parsing text from file is not possible
	 */
	#[NoAdminRequired]
	public function parseTextFromFile(?string $filePath = null, ?int $fileId = null): DataResponse {
		if ($this->userId === null) {
			return new DataResponse('Unknow user', Http::STATUS_BAD_REQUEST);
		}
		if ($fileId === null && $filePath === null) {
			return new DataResponse('Invalid parameters', Http::STATUS_BAD_REQUEST);
		}

		try {
			$text = $this->assistantService->parseTextFromFile($this->userId, $filePath, $fileId);
		} catch (\Exception|\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'parsedText' => $text,
		]);
	}

	/**
	 * Upload input file
	 *
	 * Upload an input file for a task that is being prepared
	 *
	 * @param string|null $filename The input file name
	 * @return DataResponse<Http::STATUS_OK, array{fileId: int, filePath: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: The input file was uploaded
	 * 400: Impossible to upload an input file
	 */
	#[NoAdminRequired]
	public function uploadInputFile(?string $filename = null): DataResponse {
		$inputData = $this->request->getUploadedFile('data');

		if ($inputData['error'] !== 0) {
			return new DataResponse('Error in input file upload: ' . $inputData['error'], Http::STATUS_BAD_REQUEST);
		}

		if (empty($inputData)) {
			return new DataResponse('Invalid input data received', Http::STATUS_BAD_REQUEST);
		}

		try {
			$fileInfo = $this->assistantService->storeInputFile($this->userId, $inputData['tmp_name'], $filename);
			return new DataResponse([
				'fileId' => $fileInfo['fileId'],
				'filePath' => $fileInfo['filePath'],
			]);
		} catch (\Exception $e) {
			$this->logger->error('Failed to store input file for assistant task', ['exception' => $e]);
			return new DataResponse('Failed to store the input file: ' . $e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get a file of the current user
	 *
	 * @param int $fileId The ID of the file that is requested
	 * @return DataDownloadResponse<Http::STATUS_OK, string, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 * @throws GenericFileException
	 * @throws NotPermittedException
	 * @throws LockedException
	 *
	 * 200: The file is returned
	 * 404: The file was not found
	 */
	#[NoAdminRequired]
	#[NoCsrfRequired]
	public function displayUserFile(int $fileId): DataDownloadResponse|DataResponse {
		$file = $this->assistantService->getUserFile($this->userId, $fileId);
		if ($file !== null) {
			return new DataDownloadResponse($file->getContent(), $file->getName(), $file->getMimeType());
		}
		return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
	}

	/**
	 * Get user file info
	 *
	 * Get information about a file of the current user
	 *
	 * @param int $fileId The file ID for which the info is requested
	 * @return DataResponse<Http::STATUS_OK, array{name: string, path: string, owner: string, size: int}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: The file info is returned
	 * 404: The file was not found
	 */
	#[NoAdminRequired]
	#[NoCsrfRequired]
	public function getUserFileInfo(int $fileId): DataResponse {
		$fileInfo = $this->assistantService->getUserFileInfo($this->userId, $fileId);
		if ($fileInfo !== null) {
			return new DataResponse($fileInfo);
		}
		return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
	}

	/**
	 * Share an output file
	 *
	 * Share a file that was produced by a task
	 *
	 * @param int $ocpTaskId The task ID
	 * @param int $fileId The file ID
	 * @return DataResponse<Http::STATUS_OK, array{shareToken: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: The file was shared
	 * 404: The file was not found
	 */
	#[NoAdminRequired]
	public function shareOutputFile(int $ocpTaskId, int $fileId): DataResponse {
		try {
			$shareToken = $this->assistantService->shareOutputFile($this->userId, $ocpTaskId, $fileId);
			return new DataResponse(['shareToken' => $shareToken]);
		} catch (\Exception $e) {
			$this->logger->debug('Failed to share assistant output file', ['exception' => $e]);
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get output file preview
	 *
	 * Generate and get a preview of a task output file
	 *
	 * @param int $ocpTaskId The task ID
	 * @param int $fileId The task output file ID
	 * @return DataDownloadResponse<Http::STATUS_OK, string, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>|RedirectResponse<Http::STATUS_SEE_OTHER, array{}>
	 *
	 * 200: The file preview has been generated and is returned
	 * 303: Fallback to the file type icon URL
	 * 404: The output file is not found
	 */
	#[NoAdminRequired]
	#[NoCsrfRequired]
	public function getOutputFilePreview(int $ocpTaskId, int $fileId): RedirectResponse|DataDownloadResponse|DataResponse {
		try {
			$preview = $this->assistantService->getOutputFilePreviewFile($this->userId, $ocpTaskId, $fileId);
			if ($preview === null) {
				$this->logger->error('No preview for user "' . $this->userId . '"');
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}

			if ($preview['type'] === 'file') {
				return new DataDownloadResponse(
					$preview['file']->getContent(),
					'preview',
					$preview['file']->getMimeType()
				);
			} elseif ($preview['type'] === 'icon') {
				return new RedirectResponse($preview['icon']);
			}
		} catch (Exception|Throwable $e) {
			$this->logger->error('getImage error', ['exception' => $e]);
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}
}
