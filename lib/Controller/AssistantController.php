<?php

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\AssistantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class AssistantController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private AssistantService $assistantService,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
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
}
