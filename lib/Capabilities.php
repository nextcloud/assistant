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
	 *     declarativeui?: array<string, array{
	 *         context-menu: list<array{
	 *                 name: string,
	 *                 url: string,
	 *                 method: string,
	 *                 mimetype_filters: string,
	 *                 android_icon: string,
	 *                 desktop_icon: string,
	 *                 ios_icon: string,
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

		// declarative UI
		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
		$summarizeAvailable = array_key_exists(TextToTextSummary::ID, $availableTaskTypes);
		$sttAvailable = array_key_exists(AudioToText::ID, $availableTaskTypes);
		$ttsAvailable = class_exists('OCP\\TaskProcessing\\TaskTypes\\TextToSpeech')
			&& array_key_exists(\OCP\TaskProcessing\TaskTypes\TextToSpeech::ID, $availableTaskTypes);

		if ($summarizeAvailable || $sttAvailable || $ttsAvailable) {
			$capabilities['declarativeui'] = [
				Application::APP_ID => [
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
				$url = str_replace('123456789', '{fileId}', $url);
				$endpoint = [
					'name' => $this->l->t('Summarize'),
					'url' => $url,
					'method' => 'POST',
					'mimetype_filters' => implode(', ', $textMimeTypes),
					'android_icon' => 'creation',
					'ios_icon' => 'creation',
					'desktop_icon' => 'creation',
				];
				$capabilities['declarativeui'][Application::APP_ID]['context-menu'][] = $endpoint;
			}

			if ($sttAvailable) {
				$url = $this->urlGenerator->linkToOCSRouteAbsolute(Application::APP_ID . '.assistantApi.runFileAction', [
					'apiVersion' => 'v1',
					'fileId' => '123456789',
					'taskTypeId' => \OCP\TaskProcessing\TaskTypes\TextToSpeech::ID,
				]);
				$url = str_replace('123456789', '{fileId}', $url);
				$endpoint = [
					'name' => $this->l->t('Transcribe audio'),
					'url' => $url,
					'method' => 'POST',
					'mimetype_filters' => 'audio/',
					'android_icon' => 'speech_to_text',
					'ios_icon' => 'speech_to_text',
					'desktop_icon' => 'speech_to_text',
				];
				$capabilities['declarativeui'][Application::APP_ID]['context-menu'][] = $endpoint;
			}

			if ($ttsAvailable) {
				$url = $this->urlGenerator->linkToOCSRouteAbsolute(Application::APP_ID . '.assistantApi.runFileAction', [
					'apiVersion' => 'v1',
					'fileId' => '123456789',
					'taskTypeId' => AudioToText::ID,
				]);
				$url = str_replace('123456789', '{fileId}', $url);
				$endpoint = [
					'name' => $this->l->t('Text to speech'),
					'url' => $url,
					'method' => 'POST',
					'mimetype_filters' => implode(', ', $textMimeTypes),
					'android_icon' => 'text_to_speech',
					'ios_icon' => 'text_to_speech',
					'desktop_icon' => 'text_to_speech',
				];
				$capabilities['declarativeui'][Application::APP_ID]['context-menu'][] = $endpoint;
			}
		}

		return $capabilities;
	}
}
