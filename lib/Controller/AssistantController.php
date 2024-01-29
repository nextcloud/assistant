<?php

namespace OCA\TpAssistant\Controller;

use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Service\AssistantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCA\TpAssistant\Db\TaskMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IRequest;

class AssistantController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private AssistantService $assistantService,
		private IInitialState $initialStateService,
		private ?string $userId,
		private TaskMapper $taskMapper,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $taskId
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'taskResults')]
	public function getTextProcessingTaskResultPage(int $taskId): TemplateResponse {
		$task = $this->assistantService->getTextProcessingTask($this->userId, $taskId);
				
		if ($task === null) {
			$response = new TemplateResponse(
				'',
				'403',
				[],
				TemplateResponse::RENDER_AS_ERROR
			);
			$response->setStatus(Http::STATUS_NOT_FOUND);
			$response->throttle(['userId' => $this->userId, 'taskId' => $taskId]);
			return $response;
		}
		$this->initialStateService->provideInitialState('task', $task->jsonSerializeCc());
		return new TemplateResponse(Application::APP_ID, 'taskResultPage');
	}

	/**
	 * @param int $taskId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'taskResults')]
	public function getTextProcessingResult(int $taskId): DataResponse {
		$task = $this->assistantService->getTextProcessingTask($this->userId, $taskId);

		if ($task === null) {
			$response = new DataResponse(
				'',
				Http::STATUS_NOT_FOUND
			);
			$response->throttle(['userId' => $this->userId, 'taskId' => $taskId]);
			return $response;
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
	public function runTextProcessingTask(string $type, array $inputs, string $appId, string $identifier): DataResponse {
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
		try {
			$text = $this->assistantService->parseTextFromFile($filePath);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'parsedText' => $text,
		]);
	}
}
