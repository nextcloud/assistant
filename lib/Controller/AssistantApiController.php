<?php

namespace OCA\Assistant\Controller;

use OCA\Assistant\Db\MetaTask;
use OCA\Assistant\Db\MetaTaskMapper;
use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\AssistantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\Exception;
use OCP\IRequest;

/**
 * @psalm-import-type AssistantTaskType from ResponseDefinitions
 * @psalm-import-type AssistantTask from ResponseDefinitions
 */
class AssistantApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private AssistantService $assistantService,
		private MetaTaskMapper $metaTaskMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
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
	 * Delete an assistant task
	 *
	 * This will cancel the task if needed and then delete it from the server.
	 *
	 * @param int $metaTaskId ID of the task
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, '', array{}>
	 *
	 * 200: Task deleted successfully
	 * 404: Task not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['task_management'])]
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
	 * Cancel a task
	 *
	 * This endpoint will prevent a scheduled task to run by unscheduling it
	 *
	 * @param int $metaTaskId ID of the task
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, '', array{}>
	 *
	 * 200: Task canceled successfully
	 * 404: Task not found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['task_management'])]
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
	 * Get an assistant task
	 *
	 * Get one specific task. It has to be a task owned by the current user.
	 *
	 * @param int $metaTaskId ID of the task
	 * @return DataResponse<Http::STATUS_OK, array{task: AssistantTask}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
	 *
	 * 200: Task has been found
	 * 404: Task has not been found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['task_management'])]
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

	/**
	 * Get user's tasks
	 *
	 * Get a list of assistant tasks for the current user.
	 *
	 * @param string|null $taskType Task type id. If null, tasks of all task types will be retrieved
	 * @param int|null $category Task category. If null, tasks of all categories will be retrieved
	 * @return DataResponse<Http::STATUS_OK, array{tasks: array<AssistantTask>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
	 *
	 * 200: User tasks returned
	 * 404: No tasks found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['task_management'])]
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
	 * Run a text processing task
	 *
	 * This endpoint will run the task synchronously.
	 *
	 * @param array<string, string> $inputs Input parameters
	 * @param string $type Task type id
	 * @param string $appId App id to be set in the created task
	 * @param string $identifier Identifier to be set in the created task
	 * @return DataResponse<Http::STATUS_OK, array{task: AssistantTask}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: Task started successfully
	 * 400: Running task is not possible
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['text_processing'])]
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
	 * Schedule a text processing task
	 *
	 * This endpoint will schedule the task for it to run as soon as possible.
	 *
	 * @param array<string, string> $inputs Input parameters
	 * @param string $type Task type id
	 * @param string $appId App id to be set in the created task
	 * @param string $identifier Identifier to be set in the created task
	 * @return DataResponse<Http::STATUS_OK, array{task: AssistantTask}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: Task scheduled
	 * 400: Scheduling task is not possible
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['text_processing'])]
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
	 * Run or schedule a text processing task
	 *
	 * This endpoint will either run or schedule the task.
	 *
	 * The choice between run or schedule depends on the estimated runtime declared by the actual provider that will process the task.
	 *
	 * @param array<string, string> $inputs Input parameters
	 * @param string $type Task type id
	 * @param string $appId App id to be set in the created task
	 * @param string $identifier Identifier to be set in the created task
	 * @return DataResponse<Http::STATUS_OK, array{task: AssistantTask}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 *
	 * 200: Task scheduled
	 * 400: Scheduling task is not possible
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['text_processing'])]
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
}
