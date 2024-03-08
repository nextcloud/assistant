<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Controller;

use OCA\TpAssistant\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

class Text2ImageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialStateService,
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
}
