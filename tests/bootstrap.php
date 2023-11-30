<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

//\OC::$server->get(OCP\App\IAppManager::class)->loadApp(OCA\Assistant\AppInfo\Application::APP_ID);
\OC_App::loadApp(\OCA\Assistant\AppInfo\Application::APP_ID);
OC_Hook::clear();
