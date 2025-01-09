<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Reference;

use OCA\Assistant\AppInfo\Application;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\IL10N;
use OCP\IURLGenerator;

class SpeechToTextReferenceProvider extends ADiscoverableReferenceProvider {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IReferenceManager $referenceManager,
		private ?string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'assistant_speech_to_text';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('AI audio transcription');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		return null;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the GitHub account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
