<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$appId = OCA\Assistant\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-personalSettings');
?>

<div id="assistant_prefs"></div>
