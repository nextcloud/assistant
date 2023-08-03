<?php

namespace OCA\TPAssistant\Controller;

use OCA\TPAssistant\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
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
	 * @param string $hash
	 * @return TemplateResponse
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getTaskResultPage(int $taskId): TemplateResponse {
		$this->initialStateService->provideInitialState('taskId', $taskId);
		return new TemplateResponse(Application::APP_ID, 'taskResultPage');
	}
}
