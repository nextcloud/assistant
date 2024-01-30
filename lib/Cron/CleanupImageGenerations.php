<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Cron;

use Exception;
use OCA\TpAssistant\Service\Text2Image\CleanUpService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class CleanupImageGenerations extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private CleanUpService $cleanUpService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(60 * 60 * 24);
	}

	protected function run($argument): void {
		$this->logger->debug('Run cleanup job for image generations');

		try {
			$this->cleanUpService->cleanupGenerationsAndFiles();
		} catch (Exception $e) {
			$this->logger->debug('Cleanup job for image generations failed: ' . $e->getMessage());
		}
	}
}
