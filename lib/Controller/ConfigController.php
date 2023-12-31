<?php

namespace OCA\TPAssistant\Controller;

use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\TPAssistant\AppInfo\Application;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private ?string $userId
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
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}
}
