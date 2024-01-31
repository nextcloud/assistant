<?php

use OCA\TpAssistant\AppInfo\Application;
use OCP\Util;

$appId = Application::APP_ID;
Util::addScript($appId, $appId . '-speechToTextResultPage');
