<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCP\App\IAppManager;
use OCA\TPAssistant\AppInfo\Application;

\OC::$server->get(IAppManager::class)->loadApp(Application::APP_ID);
//\OC_App::loadApp(Application::APP_ID);
OC_Hook::clear();
