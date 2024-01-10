<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TPAssistant\Controller;

use Exception;
use OCA\TPAssistant\AppInfo\Application;
use OCA\TPAssistant\Service\FreePrompt\FreePromptService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

class FreePromptController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private FreePromptService $freePromptService,
		private ?string $userId,
		private IInitialState $initialStateService
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $prompt
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function processPrompt(string $prompt, int $nResults = 1): DataResponse {
		try {
			$result = $this->freePromptService->processPrompt($prompt, $nResults);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], (int)$e->getCode());
		}
		
		return new DataResponse($result);
	}

	/**
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getPromptHistory(): DataResponse {
		try {
			$result = $this->freePromptService->getPromptHistory();
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], (int)$e->getCode());
		}
		return new DataResponse($result, Http::STATUS_OK);
	}

	/**
	 * No need for bruteforce protection since the user can only get their own generations
	 *
	 * @param string $genId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getOutputs(string $genId): DataResponse {
		try {
			$result = $this->freePromptService->getOutputs($genId);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], (int)$e->getCode());
		}
		return new DataResponse($result, Http::STATUS_OK);
	}

	/**
	 * No need for bruteforce protection since the user can only cancel their own generations
	 *
	 * @param string $genId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function cancelGeneration(string $genId): DataResponse {
		try {
			$this->freePromptService->cancelGeneration($genId);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], (int)$e->getCode());
			return $response;
		}
		return new DataResponse(['status' => 'success'], Http::STATUS_OK);
	}
}
