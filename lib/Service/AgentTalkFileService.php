<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCP\Comments\IComment;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Handles the file attachments (voice messages, images, files) shared by
 * humans in Talk conversations with agents.
 *
 * parseFileShareMessage(), getAttachmentType() and getCaptionMentions() only
 * use core APIs and are unit-testable. getRoomAttachmentNode() resolves
 * nodes and room shares; it uses internal OCA\Talk classes and is therefore
 * only called from the Talk-guarded code paths of AgentTalkService.
 */
class AgentTalkFileService {

	public const ATTACHMENT_TYPE_AUDIO = 'Audio';
	public const ATTACHMENT_TYPE_FILE = 'File';

	private const VERB_MESSAGE_FILE_SHARED = 'file_shared';
	private const MESSAGE_TYPE_VOICE_MESSAGE = 'voice-message';
	// maximum room share lookup depth in the file tree
	private const MAX_SHARE_ANCESTORS = 10;
	// maximum depth passed to IComment::setMessage() to parse mentions
	// (captions are stored truncated by Talk, this only affects parsing)
	private const MENTION_PARSE_MAX_LENGTH = 32768;

	public function __construct(
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Parse a Talk comment into a file share description, or null when the
	 * comment is not a file share.
	 *
	 * Talk file shares (voice messages, attachments) are "file_shared" system
	 * messages, created either by the classic files_sharing flow (with a
	 * "share" parameter pointing to the room share) or by the conversation
	 * draft-folder flow (with a "fileId" parameter pointing to the shared node).
	 *
	 * @return array{fileId: ?int, shareId: ?string, messageType: ?string, mimeType: ?string, caption: string}|null
	 */
	public function parseFileShareMessage(IComment $comment): ?array {
		$decoded = json_decode((string)$comment->getMessage(), true);
		if (!is_array($decoded)
			|| ($decoded['message'] ?? '') !== self::VERB_MESSAGE_FILE_SHARED
			|| !isset($decoded['parameters'])
			|| !is_array($decoded['parameters'])) {
			return null;
		}
		$parameters = $decoded['parameters'];

		$fileId = null;
		if (isset($parameters['fileId']) && is_numeric($parameters['fileId'])) {
			$fileId = (int)$parameters['fileId'];
		}
		$shareId = null;
		if (isset($parameters['share']) && is_numeric($parameters['share'])) {
			$shareId = (string)$parameters['share'];
		}
		if ($fileId === null && $shareId === null) {
			return null;
		}

		$metaData = $parameters['metaData'] ?? [];
		if (!is_array($metaData)) {
			$metaData = [];
		}

		return [
			'fileId' => $fileId,
			'shareId' => $shareId,
			'messageType' => isset($metaData['messageType']) ? (string)$metaData['messageType'] : null,
			'mimeType' => isset($metaData['mimeType']) ? (string)$metaData['mimeType'] : null,
			'caption' => isset($metaData['caption']) && is_string($metaData['caption']) ? trim($metaData['caption']) : '',
		];
	}

	/**
	 * The chat message attachment type for a Talk file share: audio files
	 * (voice messages, call recordings) are routed to the audio chat tasks,
	 * all other files to the multimodal chat tasks.
	 *
	 * @param array{messageType: ?string, mimeType: ?string} $fileShare
	 */
	public function getAttachmentType(array $fileShare, ?Node $node): string {
		$mimeType = $fileShare['mimeType'];
		if ($mimeType === null && $node instanceof File) {
			$mimeType = $node->getMimeType();
		}
		if ($fileShare['messageType'] === self::MESSAGE_TYPE_VOICE_MESSAGE
			|| ($mimeType !== null && str_starts_with($mimeType, 'audio/'))) {
			return self::ATTACHMENT_TYPE_AUDIO;
		}
		return self::ATTACHMENT_TYPE_FILE;
	}

	/**
	 * The mentions written in the caption of a Talk file share.
	 *
	 * Mentions are stored in the caption text, not in the comment message
	 * (which holds the file share JSON). Like Talk's own notifier, mentions
	 * are parsed by setting the caption as the comment message.
	 *
	 * @return list<array{type: string, id: string}>
	 */
	public function getCaptionMentions(IComment $comment, array $fileShare): array {
		$caption = $fileShare['caption'] ?? '';
		if ($caption === '') {
			return [];
		}
		$captionComment = clone $comment;
		$captionComment->setMessage($caption, self::MENTION_PARSE_MAX_LENGTH);
		return $captionComment->getMentions();
	}

	/**
	 * Resolve the node of a Talk file share, or null when the file cannot be
	 * accessed as an attachment of the room.
	 *
	 * The node is looked up in the storage of the sender (a room attachment
	 * lives in the storage of the user who shared it), and the room must have
	 * a room share on the node itself or on one of its ancestors: only then
	 * does the file belong to that Talk room.
	 *
	 * @param string $roomToken
	 * @param string $senderUserId The user id of the file share sender
	 * @param array{fileId: ?int, shareId: ?string} $fileShare Result of parseFileShareMessage()
	 */
	public function getRoomAttachmentNode(string $roomToken, string $senderUserId, array $fileShare): ?Node {
		$fileId = $fileShare['fileId'];
		if ($fileId === null && $fileShare['shareId'] !== null) {
			$fileId = $this->getNodeIdFromShare((int)$fileShare['shareId']);
			if ($fileId === null) {
				return null;
			}
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($senderUserId);
			$candidates = $userFolder->getById($fileId);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not look up the Talk room attachment in the sender storage', [
				'fileId' => $fileId,
				'senderUserId' => $senderUserId,
				'exception' => $e,
			]);
			return null;
		}

		foreach ($candidates as $candidate) {
			if (!$candidate instanceof File || !$candidate->isReadable()) {
				continue;
			}
			if ($this->isCoveredByRoomShare($roomToken, $candidate)) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * @return bool True when a room share for the given token covers the node
	 *              itself or one of its ancestors
	 */
	private function isCoveredByRoomShare(string $roomToken, Node $node): bool {
		/** @var \OCA\Talk\Share\RoomShareProvider $roomShareProvider */
		$roomShareProvider = Server::get(\OCA\Talk\Share\RoomShareProvider::class);

		$current = $node;
		for ($depth = 0; $depth < self::MAX_SHARE_ANCESTORS; $depth++) {
			try {
				$shares = $roomShareProvider->getSharesByPath($current);
			} catch (\Throwable $e) {
				$this->logger->warning('Could not check the room shares of a Talk attachment', [
					'nodeId' => $current->getId(),
					'exception' => $e,
				]);
				return false;
			}
			foreach ($shares as $share) {
				if ($share->getSharedWith() === $roomToken) {
					return true;
				}
			}
			try {
				$parent = $current->getParent();
			} catch (NotFoundException $e) {
				// the storage root was reached
				break;
			}
			if ($parent->getPath() === $current->getPath()) {
				break;
			}
			$current = $parent;
		}
		return false;
	}

	/**
	 * @return int|null The node id of the shared file
	 */
	private function getNodeIdFromShare(int $shareId): ?int {
		try {
			/** @var \OCA\Talk\Share\RoomShareProvider $roomShareProvider */
			$roomShareProvider = Server::get(\OCA\Talk\Share\RoomShareProvider::class);
			return $roomShareProvider->getShareById($shareId)->getNodeId();
		} catch (\Throwable $e) {
			$this->logger->warning('Could not resolve the Talk room share of an attachment', [
				'shareId' => $shareId,
				'exception' => $e,
			]);
			return null;
		}
	}
}
