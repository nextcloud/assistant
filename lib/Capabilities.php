<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant;

use OCA\Assistant\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;

class Capabilities implements IPublicCapability {

	public function __construct(
		private IAppManager $appManager,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IManager $taskProcessingManager,
		private IL10N $l,
		private IUrlGenerator $urlGenerator,
		private ?string $userId,
	) {
	}

	/**
	 * @return array{
	 *     assistant: array{
	 *         version: string,
	 *         enabled?: bool
	 *     },
	 *     client_integration?: array<string, array{
	 *         version: float,
	 *         context-menu: list<array{
	 *                 name: string,
	 *                 url: string,
	 *                 method: string,
	 *                 mimetype_filters: string,
	 *                 icon: string,
	 *         }>
	 *	   }>,
	 * }
	 */
	public function getCapabilities(): array {
		// App version
		$appVersion = $this->appManager->getAppVersion(Application::APP_ID);
		$capabilities = [
			Application::APP_ID => [
				'version' => $appVersion,
			],
		];
		if ($this->userId === null) {
			return $capabilities;
		}

		$adminAssistantEnabled = $this->appConfig->getValueString(Application::APP_ID, 'assistant_enabled', '1') === '1';
		$userAssistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';
		$assistantEnabled = $adminAssistantEnabled && $userAssistantEnabled;
		$capabilities[Application::APP_ID]['enabled'] = $assistantEnabled;

		// client integration UI
		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
		$summarizeAvailable = array_key_exists(TextToTextSummary::ID, $availableTaskTypes);
		$sttAvailable = array_key_exists(AudioToText::ID, $availableTaskTypes);
		$ttsAvailable = class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech')
			&& array_key_exists(\OCP\TaskProcessing\TaskTypes\TextToSpeech::ID, $availableTaskTypes);

		if ($summarizeAvailable || $sttAvailable || $ttsAvailable) {
			$capabilities['client_integration'] = [
				Application::APP_ID => [
					'version' => 0.1,
					'context-menu' => [],
				],
			];

			$textMimeTypes = [
				'text/',
				'application/msword',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/vnd.oasis.opendocument.text',
				'application/pdf',
			];
			if ($summarizeAvailable) {
				$url = $this->urlGenerator->linkToOCSRouteAbsolute(Application::APP_ID . '.assistantApi.runFileAction', [
					'apiVersion' => 'v1',
					'fileId' => '123456789',
					'taskTypeId' => TextToTextSummary::ID,
				]);
				$url = str_replace($this->urlGenerator->getBaseUrl(), '', $url);
				$url = str_replace('123456789', '{fileId}', $url);
				$endpoint = [
					'name' => $this->l->t('Summarize'),
					'url' => $url,
					'method' => 'POST',
					'mimetype_filters' => implode(', ', $textMimeTypes),
					'icon' => $this->urlGenerator->imagePath(Application::APP_ID, 'client_integration/summarize.svg'),
				];
				$capabilities['client_integration'][Application::APP_ID]['context-menu'][] = $endpoint;
			}

			if ($sttAvailable) {
				$url = $this->urlGenerator->linkToOCSRouteAbsolute(Application::APP_ID . '.assistantApi.runFileAction', [
					'apiVersion' => 'v1',
					'fileId' => '123456789',
					'taskTypeId' => AudioToText::ID,
				]);
				$url = str_replace($this->urlGenerator->getBaseUrl(), '', $url);
				$url = str_replace('123456789', '{fileId}', $url);
				$endpoint = [
					'name' => $this->l->t('Transcribe audio'),
					'url' => $url,
					'method' => 'POST',
					'mimetype_filters' => 'audio/',
					'icon' => $this->urlGenerator->imagePath(Application::APP_ID, 'client_integration/speech_to_text.svg'),
				];
				$capabilities['client_integration'][Application::APP_ID]['context-menu'][] = $endpoint;
			}

			if ($ttsAvailable) {
				$url = $this->urlGenerator->linkToOCSRouteAbsolute(Application::APP_ID . '.assistantApi.runFileAction', [
					'apiVersion' => 'v1',
					'fileId' => '123456789',
					'taskTypeId' => \OCP\TaskProcessing\TaskTypes\TextToSpeech::ID,
				]);
				$url = str_replace($this->urlGenerator->getBaseUrl(), '', $url);
				$url = str_replace('123456789', '{fileId}', $url);
				$endpoint = [
					'name' => $this->l->t('Text to speech'),
					'url' => $url,
					'method' => 'POST',
					'mimetype_filters' => implode(', ', $textMimeTypes),
					'icon' => $this->urlGenerator->imagePath(Application::APP_ID, 'client_integration/text_to_speech.svg'),
				];
				$capabilities['client_integration'][Application::APP_ID]['context-menu'][] = $endpoint;
			}
		}

		return $capabilities;
	}
}
