<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Cron;

use Exception;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\TaskMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CleanupAssistantTasks extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private LoggerInterface $logger,
		private TaskMapper $taskMapper,
	) {
		parent::__construct($time);
		$this->setInterval(60 * 60 * 24);
	}

	protected function run($argument): void {
		$this->logger->debug('Run cleanup job for assistant tasks');

		try {
			$this->taskMapper->cleanupOldTasks(Application::DEFAULT_ASSISTANT_TASK_IDLE_TIME);
		} catch (\OCP\Db\Exception | RuntimeException | Exception $e) {
			$this->logger->debug('Cleanup job for assistant tasks failed: ' . $e->getMessage());
		}
	}
}
