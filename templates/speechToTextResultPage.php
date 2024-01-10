<?php

use OCA\TPAssistant\AppInfo\Application;
use OCP\Util;

$appId = Application::APP_ID;
Util::addScript($appId, $appId . '-speechToTextResultPage');

?>

<div id="assistant-stt-content"></div>