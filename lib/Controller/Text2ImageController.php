<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\Text2Image\Text2ImageHelperService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception as DbException;
use OCP\IL10N;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class Text2ImageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private Text2ImageHelperService $text2ImageHelperService,
		private IL10N $l10n,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Show visibility dialog
	 *
	 * Does not need bruteforce protection
	 *
	 * @param string|null $imageGenId
	 * @param bool|null $forceEditMode
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
}
