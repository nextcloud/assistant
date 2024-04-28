<?php

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
use OCP\IL10N;
use OCP\IRequest;
use OCP\TaskProcessing\Task;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @psalm-import-type AssistantTaskType from ResponseDefinitions
 * @psalm-import-type AssistantTask from ResponseDefinitions
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
	 * @return DataResponse<Http::STATUS_OK, array{types: array<AssistantTaskType>}, array{}>
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
	 * @return DataResponse<Http::STATUS_OK, array{tasks: array<AssistantTask>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
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
	 * @param string $filePath Path of the file to parse in the user's storage
	 * @return DataResponse<Http::STATUS_OK, array{parsedText: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: Text parsed from file successfully
	 * 400: Parsing text from file is not possible
	 */
	#[NoAdminRequired]
	public function parseTextFromFile(string $filePath): DataResponse {
		if ($this->userId === null) {
			return new DataResponse('Unknow user', Http::STATUS_BAD_REQUEST);
		}

		try {
			$text = $this->assistantService->parseTextFromFile($filePath, $this->userId);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'parsedText' => $text,
		]);
	}

	#[NoAdminRequired]
	public function uploadInputFile(?string $extension = null): DataResponse {
		$inputData = $this->request->getUploadedFile('data');

		if ($inputData['error'] !== 0) {
			return new DataResponse('Error in input file upload: ' . $inputData['error'], Http::STATUS_BAD_REQUEST);
		}

		if (empty($inputData)) {
			return new DataResponse('Invalid input data received', Http::STATUS_BAD_REQUEST);
		}

		$fileInfo = $this->assistantService->storeInputFile($this->userId, $inputData['tmp_name'], $extension);
		return new DataResponse([
			'fileId' => $fileInfo['fileId'],
			'filePath' => $fileInfo['filePath'],
		]);
	}

	#[NoAdminRequired]
	#[NoCsrfRequired]
	public function displayUserFile(int $fileId): DataDownloadResponse|DataResponse {
		$file = $this->assistantService->getUserFile($this->userId, $fileId);
		if ($file !== null) {
			return new DataDownloadResponse($file->getContent(), $file->getName(), $file->getMimeType());
		}
		return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
	}

	#[NoAdminRequired]
	#[NoCsrfRequired]
	public function getUserFileInfo(int $fileId): DataResponse {
		$fileInfo = $this->assistantService->getUserFileInfo($this->userId, $fileId);
		if ($fileInfo !== null) {
			return new DataResponse($fileInfo);
		}
		return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
	}

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
					(string)Http::STATUS_OK,
					$preview['file']->getMimeType()
				);
			} elseif ($preview['type'] === 'icon') {
				return new RedirectResponse($preview['icon']);
			}
		} catch (Exception | Throwable $e) {
			$this->logger->error('getImage error', ['exception' => $e]);
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}
}
