<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Command;

use Exception;
use OC\Core\Command\Base;
use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\Text2Image\CleanUpService;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupImageGenerations extends Base {
	public function __construct(
		private CleanUpService $cleanUpService,
		private IConfig $config
	) {
		parent::__construct();
	}

	protected function configure() {
		$maxIdleTimeSetting = intval($this->config->getAppValue(
			Application::APP_ID,
			'max_generation_idle_time',
			strval(Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME)
		) ?: Application::DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME);
		$this->setName('assistant:image_cleanup')
			->setDescription('Cleanup image generation data')
			->addArgument(
				'max_age',
				InputArgument::OPTIONAL,
				'The max idle time (in seconds)',
				$maxIdleTimeSetting
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$maxAge = intval($input->getArgument('max_age'));

		if ($maxAge < 1) {
			$output->writeln('Invalid value for max_age: ' . $maxAge);
			return 1;
		}

		$output->writeln('Cleanning up image generation data older than ' . $maxAge . ' seconds.');
		try {
			$cleanedUp = $this->cleanUpService->cleanupGenerationsAndFiles($maxAge);
		} catch (Exception $e) {
			$output->writeln('Error: ' . $e->getMessage());
			return 1;
		}

		$output->writeln('Deleted ' . $cleanedUp['deleted_generations'] .
			' idle generations and ' . $cleanedUp['deleted_files'] . ' files.');
		if ($cleanedUp['file_deletion_errors']) {
			$output->writeln('Deletion of ' . $cleanedUp['file_deletion_errors'] . ' generations failed.');
			return 1;
		}
		return 0;
	}
}
