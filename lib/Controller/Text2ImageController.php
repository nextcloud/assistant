<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Controller;

use Exception;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Service\Text2Image\Text2ImageHelperService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Db\Exception as DbException;

use OCP\IL10N;
use OCP\IRequest;
use OCP\TextToImage\Exception\TaskFailureException;

class Text2ImageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private Text2ImageHelperService $text2ImageHelperService,
		private IInitialState $initialStateService,
		private ?string $userId,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $appId
	 * @param string $identifier
	 * @param string $prompt
	 * @param int $nResults
	 * @param bool $displayPrompt
	 * @param bool $notifyReadyIfScheduled
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function processPrompt(
		string $appId, string $identifier, string $prompt, int $nResults = 1, bool $displayPrompt = false,
		bool $notifyReadyIfScheduled = false
	): DataResponse {
		$nResults = min(10, max(1, $nResults));
		try {
			$result = $this->text2ImageHelperService->processPrompt(
				$appId, $identifier, $prompt, $nResults, $displayPrompt, $this->userId, $notifyReadyIfScheduled
			);
		} catch (Exception | TaskFailureException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
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
			$response = $this->text2ImageHelperService->getPromptHistory($this->userId);
		} catch (DbException $e) {
			return new DataResponse(['error' => $this->l10n->t('Unknown error while retrieving prompt history.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse($response);
	}

	/**
	 * @param string $imageGenId
	 * @param int $fileNameId
	 * @return DataDisplayResponse | DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'imageGenId')]
	public function getImage(string $imageGenId, int $fileNameId): DataDisplayResponse | DataResponse {
		try {
			$result = $this->text2ImageHelperService->getImage($imageGenId, $fileNameId);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], (int) $e->getCode());
			if ($e->getCode() === Http::STATUS_BAD_REQUEST || $e->getCode() === Http::STATUS_UNAUTHORIZED) {
				// Throttle brute force attempts
				$response->throttle(['action' => 'imageGenId']);
			}
			return $response;
		}

		/*
		if (isset($result['processing'])) {
			return new DataResponse($result, Http::STATUS_OK);
		}
		*/

		return new DataDisplayResponse(
			$result['image'] ?? '',
			Http::STATUS_OK,
			['Content-Type' => $result['content-type'] ?? 'image/jpeg']
		);
	}

	/**
	 * @param string $imageGenId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'imageGenId')]
	public function getGenerationInfo(string $imageGenId): DataResponse {
		try {
			$result = $this->text2ImageHelperService->getGenerationInfo($imageGenId, $this->userId, true);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], (int) $e->getCode());
			if ($e->getCode() === Http::STATUS_BAD_REQUEST || $e->getCode() === Http::STATUS_UNAUTHORIZED) {
				// Throttle brute force attempts
				$response->throttle(['action' => 'imageGenId']);
			}
			return $response;
		}

		return new DataResponse($result, Http::STATUS_OK);
	}

	/**
	 * @param string $imageGenId
	 * @param array $fileVisStatusArray
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'imageGenId')]
	public function setVisibilityOfImageFiles(string $imageGenId, array $fileVisStatusArray): DataResponse {

		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to set visibility of image files; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if (count($fileVisStatusArray) < 1) {
			return new DataResponse('File visibility array empty', Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->text2ImageHelperService->setVisibilityOfImageFiles($imageGenId, $fileVisStatusArray, $this->userId);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], (int) $e->getCode());
			if($e->getCode() === Http::STATUS_BAD_REQUEST || $e->getCode() === Http::STATUS_UNAUTHORIZED) {
				// Throttle brute force attempts
				$response->throttle(['action' => 'imageGenId']);
			}
			return $response;
		}

		return new DataResponse('success', Http::STATUS_OK);
	}

	/**
	 * Notify when image generation is ready
	 *
	 * Does not need bruteforce protection since we respond with success anyways
	 * as we don't want to keep the front-end waiting.
	 * However, we still use rate limiting to prevent timing attacks.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function notifyWhenReady(string $imageGenId): DataResponse {

		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to notify when ready; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$this->text2ImageHelperService->notifyWhenReady($imageGenId, $this->userId);
		} catch (Exception $e) {
			// Ignore
		}
		return new DataResponse('success', Http::STATUS_OK);
	}
	/**
	 * Cancel image generation
	 *
	 * Does not need bruteforce protection since we respond with success anyways
	 * (In theory bruteforce may be possible by a response timing attack but the attacker
	 * won't gain access to the generation since its deleted during the attack.)
	 *
	 * @param string $imageGenId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function cancelGeneration(string $imageGenId): DataResponse {

		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l10n->t('Failed to cancel generation; unknown user')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$this->text2ImageHelperService->cancelGeneration($imageGenId, $this->userId);
		return new DataResponse('success', Http::STATUS_OK);
	}

	/**
	 * Show visibility dialog
	 *
	 * Does not need bruteforce protection
	 *
	 * @param string|null $imageGenId
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function showGenerationPage(?string $imageGenId, ?bool $forceEditMode = false): TemplateResponse {
		if ($forceEditMode === null) {
			$forceEditMode = false;
		}
		$this->initialStateService->provideInitialState('generation-page-inputs', ['image_gen_id' => $imageGenId, 'force_edit_mode' => $forceEditMode]);

		return new TemplateResponse(Application::APP_ID, 'imageGenerationPage');
	}
}
