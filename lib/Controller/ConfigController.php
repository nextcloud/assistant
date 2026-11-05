<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Service\AgentSkillsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\PreConditionNotMetException;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IAppConfig $appConfig,
		private AgentSkillsService $agentSkillsService,
		private ?string $userId,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Set config values
	 *
	 * @param array $values key/value pairs to store in config
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * @param string $key
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getConfigValue(string $key): DataResponse {
		$value = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
		return new DataResponse($value);
	}

	/**
	 * Set admin config values
	 *
	 * @param array $values key/value pairs to store in app config
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			// do not lazy store assistant_enabled as it is needed for capabilities
			$storedLazy = $key !== 'assistant_enabled';
			$oldValue = $this->appConfig->hasKey(Application::APP_ID, $key, $storedLazy)
				? $this->appConfig->getValueString(Application::APP_ID, $key, '', $storedLazy)
				: null;
			$this->appConfig->setValueString(Application::APP_ID, $key, $value, lazy: $storedLazy);
			if ($oldValue === $value) {
				continue;
			}
			$this->audit(
				'The assistant admin setting "%s" was changed from "%s" to "%s" by "%s"',
				[
					'setting' => $key,
					'oldValue' => $oldValue,
					'newValue' => $value,
					'user' => $this->userId,
				],
				'assistant.admin_setting.updated',
			);
		}
		return new DataResponse(1);
	}

	/**
	 * Set (or clear) the admin-configured global skills folder.
	 *
	 * The folder is resolved against the current admin user's filesystem. Pass an empty
	 * `path` to clear the configuration.
	 *
	 * @param string $path Folder path inside the current admin user's home, as returned by the file picker
	 * @return DataResponse
	 */
	public function setGlobalSkillsFolder(string $path): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		}
		$path = trim($path);
		$newAdminUid = $path === '' ? '' : $this->userId;
		$old = $this->agentSkillsService->getGlobalSkillsConfig();
		$oldAdminUid = $old['admin_uid'];
		$oldPath = $old['path'];
		if ($oldAdminUid === $newAdminUid && $oldPath === $path) {
			return new DataResponse(1);
		}
		$this->agentSkillsService->setGlobalSkillsFolder($newAdminUid, $path);
		$this->audit(
			'The assistant global skills folder configured by "%s" was changed from "%s" (admin: "%s") to "%s" (admin: "%s")',
			[
				'user' => $this->userId,
				'oldPath' => $oldPath,
				'oldAdminUid' => $oldAdminUid,
				'newPath' => $path,
				'newAdminUid' => $newAdminUid,
			],
			'assistant.global_skills_folder.updated',
		);
		return new DataResponse(1);
	}

	/**
	 * Log a critical action to the admin audit log
	 *
	 * The `$operation` parameter of `CriticalActionPerformedEvent` only exists since Nextcloud 36;
	 * on Nextcloud 35 the extra constructor argument is silently ignored by PHP. The app's pinned
	 * OCP stubs predate the extra parameter, hence the psalm suppression.
	 */
	private function audit(string $message, array $params, string $operation): void {
		/** @psalm-suppress TooManyArguments */
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			$message,
			$params,
			false,
			$operation,
		));
	}
}
