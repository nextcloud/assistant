<?php

namespace OCA\TPAssistant\Service;

use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class AssistantService {

	public function __construct(
		string $appName,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
	) {
	}

}
