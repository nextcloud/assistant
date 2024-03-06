<?php
/**
 * @copyright Copyright (c) 2023 Anupam Kumar <kyteinsky@gmail.com>
 *
 * @author Anupam Kumar <kyteinsky@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\TpAssistant\Controller;

use Exception;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Service\SpeechToText\SpeechToTextService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

class SpeechToTextController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private SpeechToTextService $service,
		private IInitialState $initialState,
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
	public function getResultPage(int $metaTaskId): TemplateResponse {
		if ($this->userId === null) {
			return new TemplateResponse('', '403', [], TemplateResponse::RENDER_AS_ERROR, Http::STATUS_FORBIDDEN);
		}
		$response = new TemplateResponse(Application::APP_ID, 'speechToTextResultPage');
		try {
			$initData = [
				'task' => $this->service->internalGetTask($this->userId, $metaTaskId),
			];
		} catch (Exception $e) {
			$initData = [
				'status' => 'failure',
				'task' => null,
				'message' => $e->getMessage(),
			];
			$response->setStatus(intval($e->getCode()));
		}
		$this->initialState->provideInitialState('plain-text-result', $initData);
		return $response;
	}
}
