<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCA\TPAssistant\AppInfo\Application;
use OCP\App\IAppManager;

\OC::$server->get(IAppManager::class)->loadApp(Application::APP_ID);
//\OC_App::loadApp(Application::APP_ID);
OC_Hook::clear();
