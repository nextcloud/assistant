<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCA\Assistant\AppInfo\Application;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\ShapeDescriptor;
use Psr\Log\LoggerInterface;

/**
 * Decides whether a file can be sent to the AI provider as-is (as a task
 * "input_attachments" file) instead of having its text extracted by Nextcloud
 * first.
 *
 * Sending the file itself lets the model read documents that our own parsers
 * handle badly or not at all (scanned PDFs, complex layouts) and sidesteps the
 * 512 kB limit that core puts on Text-shaped task inputs.
 */
class AttachmentService {

	/**
	 * Mime types that are always worth sending to the provider as a file.
	 *
	 * Keep this in sync with what the providers can actually turn into a
	 * document part. Office formats are deliberately absent: they are rejected
	 * provider side, so they have to keep going through text extraction.
	 */
	public const ATTACHABLE_MIME_TYPES = [
		'application/pdf',
	];

	/**
	 * Mime types we can extract losslessly and cheaply ourselves. These are
	 * only attached when they are too big to fit in a Text input.
	 */
	public const EXTRACTABLE_TEXT_MIME_TYPES = [
		'text/plain',
		'text/markdown',
		'text/csv',
	];

	/**
	 * Above this size, extracting a text file locally risks blowing the 512 kB
	 * limit that core enforces on Text-shaped inputs, so attach it instead.
	 */
	private const DEFAULT_TEXT_INLINE_THRESHOLD = 262_144;

	/** Mirrors the maximum input file size accepted by the providers. */
	private const DEFAULT_MAX_ATTACHMENT_SIZE = 50_000_000;

	public function __construct(
		private IManager $taskProcessingManager,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Whether the provider currently selected for $taskTypeId accepts files
	 * alongside the task input.
	 *
	 * Optional input shapes come from the provider rather than the task type,
	 * so this is how a provider advertises that it can handle attachments.
	 */
	public function providerSupportsInputAttachments(string $taskTypeId): bool {
		try {
			$taskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
			$shape = $taskTypes[$taskTypeId]['optionalInputShape']['input_attachments'] ?? null;
			return $shape instanceof ShapeDescriptor
				&& $shape->getShapeType() === EShapeType::ListOfFiles;
		} catch (\Throwable $e) {
			// this is also called while rendering templates, never let it bubble up
			$this->logger->debug('Could not determine attachment support for ' . $taskTypeId, ['exception' => $e]);
			return false;
		}
	}

	/**
	 * Whether $file is one we would rather hand to the model directly.
	 */
	public function isAttachableFile(File $file): bool {
		if ($file->getSize() > $this->getMaxAttachmentSize()) {
			return false;
		}
		$mimeType = $file->getMimeType();
		if (in_array($mimeType, self::ATTACHABLE_MIME_TYPES, true)) {
			return true;
		}
		return in_array($mimeType, self::EXTRACTABLE_TEXT_MIME_TYPES, true)
			&& $file->getSize() > $this->getTextInlineThreshold();
	}

	/**
	 * Whether the file behind $fileId should be attached to a $taskTypeId task
	 * rather than parsed into text first.
	 */
	public function shouldAttachFile(File $file, string $taskTypeId): bool {
		return $this->providerSupportsInputAttachments($taskTypeId)
			&& $this->isAttachableFile($file);
	}

	/**
	 * The mime types the summarize file action can handle over and above the
	 * ones we can extract text from.
	 *
	 * These end up in a file action `enabled()` check that compares mime types
	 * exactly, so only concrete types belong here - no wildcards.
	 *
	 * @return list<string>
	 */
	public function getAttachableMimeTypes(string $taskTypeId): array {
		if (!$this->providerSupportsInputAttachments($taskTypeId)) {
			return [];
		}
		return self::ATTACHABLE_MIME_TYPES;
	}

	private function getMaxAttachmentSize(): int {
		return $this->appConfig->getValueInt(
			Application::APP_ID,
			'max_attachment_size',
			self::DEFAULT_MAX_ATTACHMENT_SIZE,
			lazy: true,
		);
	}

	private function getTextInlineThreshold(): int {
		return $this->appConfig->getValueInt(
			Application::APP_ID,
			'text_inline_threshold',
			self::DEFAULT_TEXT_INLINE_THRESHOLD,
			lazy: true,
		);
	}
}
