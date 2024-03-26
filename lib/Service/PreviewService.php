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

use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
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
	 * @param int $x
	 * @param int $y
	 * @return array|null
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function getFilePreviewFile(int $fileId, string $userId, int $x = 100, int $y = 100): ?array {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (count($files) > 0 && $files[0] instanceof File) {
			$file = $files[0];
			if ($this->previewManager->isMimeSupported($file->getMimeType())) {
				try {
					return [
						'type' => 'file',
						'file' => $this->previewManager->getPreview($file, $x, $y),
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
		return null;
	}
}
