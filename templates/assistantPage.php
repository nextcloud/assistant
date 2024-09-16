<?php

$appId = OCA\Assistant\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-assistantPage');
\OCP\Util::addStyle($appId, $appId . '-assistantPage');
