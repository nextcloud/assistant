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

use OCP\IRequest;

class AssistantController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private AssistantService $assistantService,
		private IInitialState $initialStateService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $taskId
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'taskResultPage')]
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
		$this->initialStateService->provideInitialState('task', $task->jsonSerialize());
		return new TemplateResponse(Application::APP_ID, 'taskResultPage');
	}

	/**
	 * @param string $input
	 * @param string $type
	 * @param string $appId
	 * @param string $identifier
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function runTextProcessingTask(string $type, string $input, string $appId, string $identifier): DataResponse {
		try {
			$task = $this->assistantService->runTextProcessingTask($type, $input, $appId, $this->userId, $identifier);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'task' => $task->jsonSerialize(),
		]);
	}

	/**
	 * @param string $input
	 * @param string $type
	 * @param string $appId
	 * @param string $identifier
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function runOrScheduleTextProcessingTask(string $type, string $input, string $appId, string $identifier): DataResponse {
		try {
			$task = $this->assistantService->runOrScheduleTextProcessingTask($type, $input, $appId, $this->userId, $identifier);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([
			'task' => $task->jsonSerialize(),
		]);
	}
}
