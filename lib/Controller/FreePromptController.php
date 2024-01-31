<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Controller;

use Exception;
use OCA\TpAssistant\Service\FreePrompt\FreePromptService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IInitialState;

use OCP\IL10N;
use OCP\IRequest;

class FreePromptController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private FreePromptService $freePromptService,
		private ?string $userId,
		private IInitialState $initialStateService,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $prompt
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function processPrompt(string $prompt): DataResponse {

		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to process prompt; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$result = $this->freePromptService->processPrompt($prompt, $this->userId);
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

		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to get prompt history; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$result = $this->freePromptService->getPromptHistory($this->userId);
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

		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to get outputs; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$result = $this->freePromptService->getOutputs($genId, $this->userId);
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

		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to cancel generation; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$this->freePromptService->cancelGeneration($genId, $this->userId);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], (int)$e->getCode());
			return $response;
		}
		return new DataResponse(['status' => 'success'], Http::STATUS_OK);
	}
}
