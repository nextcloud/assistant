<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TPAssistant\Controller;

use Exception;
use OCP\Db\Exception as DbException;
use OCA\TPAssistant\AppInfo\Application;
use OCA\TPAssistant\Service\Text2Image\Text2ImageHelperService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\TextToImage\Exception\TaskFailureException;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;


class Text2ImageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private Text2ImageHelperService $text2ImageHelperService,
		private IInitialState $initialStateService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $prompt
	 * @param int $nResults
	 * @param bool $displayPrompt
	 * @return DataResponse
	 */
	public function processPrompt(string $prompt, int $nResults = 1, bool $displayPrompt = false): DataResponse {
		$nResults = min(10, max(1, $nResults));
		try {
			$result = $this->text2ImageHelperService->processPrompt($prompt, $nResults, $displayPrompt);
		} catch (Exception | TaskFailureException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function getPromptHistory(): DataResponse {
		try {
			$response = $this->text2ImageHelperService->getPromptHistory();
		} catch (DbException $e) {
			return new DataResponse(['error' => 'Unknown error while retrieving prompt history.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse($response);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $imageGenId
	 * @param int $fileNameId
	 * @return DataDisplayResponse | DataResponse
	 */
	#[BruteForceProtection(action: 'imageGenId')]
	public function getImage(string $imageGenId, int $fileNameId): DataDisplayResponse | DataResponse {

		try {
			$result = $this->text2ImageHelperService->getImage($imageGenId, $fileNameId);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], $e->getCode());
			if ($e->getCode() === Http::STATUS_BAD_REQUEST | Http::STATUS_UNAUTHORIZED) {
				// Throttle brute force attempts
				$response->throttle(['action' => 'imageGenId']);
			}
			return $response;
		}

		if (isset($result['processing'])) {
			return new DataResponse($result, Http::STATUS_OK);
		}

		return new DataDisplayResponse(
			$result['image'] ?? '',
			Http::STATUS_OK,
			['Content-Type' => $result['content-type'] ?? 'image/jpeg']
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $imageGenId
	 * @return DataResponse
	 */
	#[BruteForceProtection(action: 'imageGenId')]
	public function getGenerationInfo(string $imageGenId): DataResponse {
		try {
			$result = $this->text2ImageHelperService->getGenerationInfo($imageGenId, true);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], $e->getCode());
			if ($e->getCode() === Http::STATUS_BAD_REQUEST | Http::STATUS_UNAUTHORIZED) {
				// Throttle brute force attempts
				$response->throttle(['action' => 'imageGenId']);
			}
			return $response;
		}

		return new DataResponse($result, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $imageGenId
	 * @param array $fileVisStatusArray
	 */
	#[BruteForceProtection(action: 'imageGenId')]
	public function setVisibilityOfImageFiles(string $imageGenId, array $fileVisStatusArray): DataResponse {
		if (count($fileVisStatusArray) < 1) {
			return new DataResponse('File visibility array empty', Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->text2ImageHelperService->setVisibilityOfImageFiles($imageGenId, $fileVisStatusArray);
		} catch (Exception $e) {
			$response = new DataResponse(['error' => $e->getMessage()], $e->getCode());
			if($e->getCode() === Http::STATUS_BAD_REQUEST | Http::STATUS_UNAUTHORIZED) {
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
	 * 
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[AnonRateLimit(limit: 10, period: 60)]
	public function notifyWhenReady(string $imageGenId): DataResponse {
		try {
			$this->text2ImageHelperService->notifyWhenReady($imageGenId);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $imageGenId
	 * @return DataResponse
	 */
	#[AnonRateLimit(limit: 10, period: 60)]
	public function cancelGeneration(string $imageGenId): DataResponse {
		$this->text2ImageHelperService->cancelGeneration($imageGenId);
		return new DataResponse('success', Http::STATUS_OK);
	}

	/**
	 * Show visibility dialog
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * Does not need bruteforce protection
	 *
	 * @param string|null $imageGenId
	 * @return TemplateResponse
	 */
	public function showGenerationPage(?string $imageGenId, ?bool $forceEditMode = false): TemplateResponse {
		if ($forceEditMode === null) {
			$forceEditMode = false;
		}
		if ($imageGenId === null) {
			$this->initialStateService->provideInitialState('generation-page-inputs', ['image_gen_id' => $imageGenId, 'force_edit_mode' => $forceEditMode]);
		} else {
			$this->initialStateService->provideInitialState('generation-page-inputs', ['image_gen_id' => $imageGenId, 'force_edit_mode' => $forceEditMode]);
		}		

		return new TemplateResponse(Application::APP_ID, 'imageGenerationPage');
	}
}
