<?php

namespace OCA\TpAssistant\Controller;

use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Service\AssistantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

class AssistantController extends Controller {

	public function __construct(
		string                   $appName,
		IRequest                 $request,
		private AssistantService $assistantService,
		private IInitialState    $initialStateService,
		private ?string          $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $taskId
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getTextProcessingTaskResultPage(int $taskId): TemplateResponse {

		if ($this->userId !== null) {
			$task = $this->assistantService->getTextProcessingTask($this->userId, $taskId);
			if ($task !== null) {
				$this->initialStateService->provideInitialState('task', $task->jsonSerializeCc());
				return new TemplateResponse(Application::APP_ID, 'taskResultPage');
			}
		}
		return new TemplateResponse('', '403', [], TemplateResponse::RENDER_AS_ERROR, Http::STATUS_FORBIDDEN);
	}

	/**
	 * @param int $taskId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getTextProcessingResult(int $taskId): DataResponse {

		if ($this->userId !== null) {
			$task = $this->assistantService->getTextProcessingTask($this->userId, $taskId);
			if ($task !== null) {
				return new DataResponse([
					'task' => $task->jsonSerializeCc(),
				]);
			}
		}
		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}

	/**
	 * @param array $inputs
	 * @param string $type
	 * @param string $appId
	 * @param string $identifier
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function runTextProcessingTask(string $type, array $inputs, string $appId, string $identifier): DataResponse {
		if ($this->userId === null) {
			return new DataResponse('Unknow user', Http::STATUS_BAD_REQUEST);
		}

		try {
			$task = $this->assistantService->runTextProcessingTask($type, $inputs, $appId, $this->userId, $identifier);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'task' => $task->jsonSerializeCc(),
		]);
	}

	/**
	 * @param array $inputs
	 * @param string $type
	 * @param string $appId
	 * @param string $identifier
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function scheduleTextProcessingTask(string $type, array $inputs, string $appId, string $identifier): DataResponse {
		if ($this->userId === null) {
			return new DataResponse('Unknow user', Http::STATUS_BAD_REQUEST);
		}

		try {
			$task = $this->assistantService->scheduleTextProcessingTask($type, $inputs, $appId, $this->userId, $identifier);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'task' => $task->jsonSerializeCc(),
		]);
	}

	/**
	 * @param array $inputs
	 * @param string $type
	 * @param string $appId
	 * @param string $identifier
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function runOrScheduleTextProcessingTask(string $type, array $inputs, string $appId, string $identifier): DataResponse {
		if ($this->userId === null) {
			return new DataResponse('Unknow user', Http::STATUS_BAD_REQUEST);
		}

		try {
			$task = $this->assistantService->runOrScheduleTextProcessingTask($type, $inputs, $appId, $this->userId, $identifier);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'task' => $task->jsonSerializeCc(),
		]);
	}

	/**
	 * Parse text from file (if parsing the file type is supported)
	 *
	 * @param string $filePath
	 * @return DataResponse
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
}
