<?php
/**
 * Nextcloud - Assistant
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Assistant\Controller;

use Exception;
use OCA\Assistant\Service\PreviewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PreviewController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private PreviewService $imageService,
		private LoggerInterface $logger,
		private ?string  $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $id
	 * @param int $x
	 * @param int $y
	 * @return DataDownloadResponse|DataResponse|RedirectResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getFileImage(int $id, int $x = 100, int $y = 100): Response {
		try {
			$preview = $this->imageService->getFilePreviewFile($id, $this->userId, $x, $y);
			if ($preview === null) {
				$this->logger->error('No preview for user "' . $this->userId . '"');
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}

			if ($preview['type'] === 'file') {
				return new DataDownloadResponse(
					$preview['file']->getContent(),
					(string)Http::STATUS_OK,
					$preview['file']->getMimeType()
				);
			} elseif ($preview['type'] === 'icon') {
				return new RedirectResponse($preview['icon']);
			}
		} catch (Exception | Throwable $e) {
			$this->logger->error('getImage error', ['exception' => $e]);
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		return new DataResponse('', Http::STATUS_NOT_FOUND);
	}
}
