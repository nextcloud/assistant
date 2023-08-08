<?php

namespace OCA\TPAssistant\Controller;

use OCA\TPAssistant\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

use OCA\TPAssistant\Service\AssistantService;

class AssistantController extends Controller {

	public function __construct(
		string                   $appName,
		IRequest                 $request,
		private AssistantService $assistantService,
		private IInitialState    $initialStateService,
		private ?string          $userId
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
	public function getTaskResultPage(int $taskId): TemplateResponse {
		$task = $this->assistantService->getTask($this->userId, $taskId);
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
	public function runTask(string $type, string $input, string $appId, string $identifier): DataResponse {
		try {
			$output = $this->assistantService->runTask($type, $input, $appId, $this->userId, $identifier);
		} catch (\Exception | \Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($output);
	}
}
