<?php

namespace OCA\Assistant\BackgroundJob;

use OCA\Assistant\Service\SessionSummaryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class GenerateNewChatSummaries extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private SessionSummaryService $sessionSummaryService
	) {
		parent::__construct($timeFactory);
		$this->setInterval(60 * 10); // 10min
	}
	public function run($argument) {
		$userId = $argument['userId'];
		$this->sessionSummaryService->generateSummariesForNewSessions($userId);
	}
}