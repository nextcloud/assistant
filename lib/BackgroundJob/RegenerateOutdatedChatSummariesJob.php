<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\BackgroundJob;

use OCA\Assistant\Service\SessionSummaryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class RegenerateOutdatedChatSummariesJob extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private SessionSummaryService $sessionSummaryService,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(60 * 10); // 10min
	}
	public function run($argument) {
		$userId = $argument['userId'];
		$this->sessionSummaryService->regenerateSummariesForOutdatedSessions($userId);
	}
}
