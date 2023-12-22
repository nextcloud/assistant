<?php
use OCA\TPAssistant\AppInfo\Application;
use OCP\Util;

// Load the dialog javascript
Util::addScript(Application::APP_ID, Application::APP_ID . '-imageGenerationPage');
?>

<div id="text2image_generation_page"></div>