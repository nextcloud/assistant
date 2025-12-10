<?php

namespace OCA\Assistant\BackgroundJob;

use OCA\Assistant\Service\SessionSummaryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class RegenerateOutdatedChatSummariesJob extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private SessionSummaryService $sessionSummaryService
	) {
		parent::__construct($timeFactory);
		$this->setInterval(60 * 60 * 24); // 24h
	}
	public function run($argument) {
		$userId = $argument['userId'];
		$this->sessionSummaryService->regenerateSummariesForOutdatedSessions($userId);
	}
}