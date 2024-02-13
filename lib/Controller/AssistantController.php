<?php

namespace OCA\TpAssistant\Controller;

use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTask;
use OCA\TpAssistant\Db\MetaTaskMapper;
use OCA\TpAssistant\Service\AssistantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\IRequest;

class AssistantController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private AssistantService $assistantService,
		private MetaTaskMapper $metaTaskMapper,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $metaTaskId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function deleteTask(int $metaTaskId): DataResponse {
		if ($this->userId !== null) {
			try {
				$this->assistantService->deleteAssistantTask($this->userId, $metaTaskId);
				return new DataResponse('');
			} catch (\Exception $e) {
			}
		}

		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}

	/**
	 * @param int $metaTaskId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function cancelTask(int $metaTaskId): DataResponse {
		if ($this->userId !== null) {
			try {
				$this->assistantService->cancelAssistantTask($this->userId, $metaTaskId);
				return new DataResponse('');
			} catch (\Exception $e) {
			}
		}

		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}

	/**
	 * @param int $metaTaskId
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAssistantTaskResultPage(int $metaTaskId): TemplateResponse {
		if ($this->userId !== null) {
			$task = $this->assistantService->getAssistantTask($this->userId, $metaTaskId);
			if ($task !== null) {
				$this->initialStateService->provideInitialState('task', $task->jsonSerializeCc());
				return new TemplateResponse(Application::APP_ID, 'taskResultPage');
			}
		}
		return new TemplateResponse('', '403', [], TemplateResponse::RENDER_AS_ERROR, Http::STATUS_FORBIDDEN);
	}

	/**
	 * @param int $metaTaskId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getAssistantTask(int $metaTaskId): DataResponse {
		if ($this->userId !== null) {
			$task = $this->assistantService->getAssistantTask($this->userId, $metaTaskId);
			if ($task !== null) {
				return new DataResponse([
					'task' => $task->jsonSerializeCc(),
				]);
			}
		}
		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}

	#[NoAdminRequired]
	public function getUserTasks(?string $taskType = null, ?int $category = null): DataResponse {
		if ($this->userId !== null) {
			try {
				$tasks = $this->metaTaskMapper->getUserMetaTasks($this->userId, $taskType, $category);
				$serializedTasks = array_map(static function (MetaTask $task) {
					return $task->jsonSerializeCc();
				}, $tasks);
				return new DataResponse(['tasks' => $serializedTasks]);
			} catch (Exception $e) {
				return new DataResponse(['tasks' => []]);
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
