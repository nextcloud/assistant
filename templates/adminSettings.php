<?php
$appId = OCA\Assistant\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-adminSettings');
\OCP\Util::addStyle($appId, $appId . '-adminSettings');
?>

<div id="assistant_prefs"></div>
