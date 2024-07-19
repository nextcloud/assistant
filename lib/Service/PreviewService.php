<?php

/**
 * Nextcloud - Assistant
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Assistant\Service;

use OC\User\NoUserException;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IPreview;
use Psr\Log\LoggerInterface;

class PreviewService {

	public function __construct(
		private IRootFolder $root,
		private LoggerInterface $logger,
		private IPreview $previewManager,
		private IMimeTypeDetector $mimeTypeDetector,
	) {
	}

	/**
	 * @param int $fileId
	 * @param string $userId
	 * @return File|null
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function getUserFile(int $fileId, string $userId): ?File {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (count($files) > 0 && $files[0] instanceof File) {
			return $files[0];
		}
		return null;
	}

	/**
	 * @param File $file
	 * @param int $x
	 * @param int $y
	 * @param string|null $fallbackMimeType
	 * @return array|null
	 */
	public function getFilePreviewFile(File $file, int $x = 100, int $y = 100, ?string $fallbackMimeType = null): ?array {
		$mimetype = $file->getMimeType();
		if ($mimetype === 'application/octet-stream' && $fallbackMimeType !== null) {
			$mimetype = $fallbackMimeType;
		};
		if ($this->previewManager->isMimeSupported($mimetype)) {
			try {
				return [
					'type' => 'file',
					'file' => $this->previewManager->getPreview($file, $x, $y, false, IPreview::MODE_FILL, $mimetype),
				];
			} catch (NotFoundException $e) {
				$this->logger->error('Mimetype is supported but no preview available', ['exception' => $e]);
			}
		}
		// fallback: mimetype icon
		return [
			'type' => 'icon',
			'icon' => $this->mimeTypeDetector->mimeTypeIcon($file->getMimeType()),
		];
	}

	/**
	 * @param int $fileId
	 * @param string $userId
	 * @param int $x
	 * @param int $y
	 * @return array|null
	 * @throws NoUserException
	 * @throws NotPermittedException
	 */
	public function getUserFilePreviewFile(int $fileId, string $userId, int $x = 100, int $y = 100): ?array {
		$file = $this->getUserFile($fileId, $userId);
		if ($file !== null) {
			return $this->getFilePreviewFile($file, $x, $y);
		}
		return null;
	}
}
