<?php

return [
	'routes' => [
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'assistant#getplop', 'url' => '/plop', 'verb' => 'GET'],
		['name' => 'assistant#getTaskResultPage', 'url' => '/t/{taskId}', 'verb' => 'GET'],
	],
];
