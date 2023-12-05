<?php

return [
	'routes' => [
		['name' => 'config#getConfigValue', 'url' => '/config', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'assistant#getTaskResultPage', 'url' => '/t/{taskId}', 'verb' => 'GET'],
		['name' => 'assistant#runTask', 'url' => '/run', 'verb' => 'POST'],
		['name' => 'assistant#runOrScheduleTask', 'url' => '/run-or-schedule', 'verb' => 'POST'],
	],
];
