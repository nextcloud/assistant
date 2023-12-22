<?php

return [
	'routes' => [
		['name' => 'config#getConfigValue', 'url' => '/config', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'assistant#getTextProcessingTaskResultPage', 'url' => '/t/{taskId}', 'verb' => 'GET'],
		['name' => 'assistant#runTextProcessingTask', 'url' => '/run', 'verb' => 'POST'],
		['name' => 'assistant#runOrScheduleTextProcessingTask', 'url' => '/run-or-schedule', 'verb' => 'POST'],

		['name' => 'Text2Image#processPrompt', 'url' => '/i/process_prompt', 'verb' => 'POST'],
		['name' => 'Text2Image#getPromptHistory', 'url' => '/i/prompt_history', 'verb' => 'GET'],
		['name' => 'Text2Image#showGenerationPage', 'url' => '/i/{imageGenId}', 'verb' => 'GET'],
		['name' => 'Text2Image#getGenerationInfo', 'url' => '/i/info/{imageGenId}', 'verb' => 'GET'],
		['name' => 'Text2Image#getImage', 'url' => '/i/{imageGenId}/{fileNameId}', 'verb' => 'GET'],
		['name' => 'Text2Image#cancelGeneration', 'url' => '/i/cancel_generation', 'verb' => 'POST'],
		['name' => 'Text2Image#setVisibilityOfImageFiles', 'url' => '/i/visibility/{imageGenId}', 'verb' => 'POST'],
		['name' => 'Text2Image#notifyWhenReady', 'url' => '/i/notify/{imageGenId}', 'verb' => 'POST'],
	],
];
