<?php

return [
	'routes' => [
		['name' => 'config#getConfigValue', 'url' => '/config', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'assistant#getAssistantTaskResultPage', 'url' => '/task/view/{metaTaskId}', 'verb' => 'GET'],
		['name' => 'assistant#getAssistantTask', 'url' => '/task/{metaTaskId}', 'verb' => 'GET'],
		['name' => 'assistant#getUserTasks', 'url' => '/tasks', 'verb' => 'GET'],
		['name' => 'assistant#runTextProcessingTask', 'url' => '/task/run', 'verb' => 'POST'],
		['name' => 'assistant#scheduleTextProcessingTask', 'url' => '/task/schedule', 'verb' => 'POST'],
		['name' => 'assistant#runOrScheduleTextProcessingTask', 'url' => '/task/run-or-schedule', 'verb' => 'POST'],
		['name' => 'assistant#parseTextFromFile', 'url' => '/parse-file', 'verb' => 'POST'],

		['name' => 'Text2Image#processPrompt', 'url' => '/i/process_prompt', 'verb' => 'POST'],
		['name' => 'Text2Image#getPromptHistory', 'url' => '/i/prompt_history', 'verb' => 'GET'],
		['name' => 'Text2Image#showGenerationPage', 'url' => '/i/{imageGenId}', 'verb' => 'GET'],
		['name' => 'Text2Image#getGenerationInfo', 'url' => '/i/info/{imageGenId}', 'verb' => 'GET'],
		['name' => 'Text2Image#getImage', 'url' => '/i/{imageGenId}/{fileNameId}', 'verb' => 'GET'],
		['name' => 'Text2Image#cancelGeneration', 'url' => '/i/cancel_generation', 'verb' => 'POST'],
		['name' => 'Text2Image#setVisibilityOfImageFiles', 'url' => '/i/visibility/{imageGenId}', 'verb' => 'POST'],
		['name' => 'Text2Image#notifyWhenReady', 'url' => '/i/notify/{imageGenId}', 'verb' => 'POST'],

		['name' => 'FreePrompt#processPrompt', 'url' => '/f/process_prompt', 'verb' => 'POST'],
		['name' => 'FreePrompt#getPromptHistory', 'url' => '/f/prompt_history', 'verb' => 'GET'],
		['name' => 'FreePrompt#getOutputs', 'url' => '/f/get_outputs', 'verb' => 'GET'],
		['name' => 'FreePrompt#cancelGeneration', 'url' => '/f/cancel_generation', 'verb' => 'POST'],

		['name' => 'SpeechToText#getResultPage', 'url' => '/stt/result-page/{metaTaskId}', 'verb' => 'GET'],
		['name' => 'SpeechToText#transcribeAudio', 'url' => '/stt/transcribeAudio', 'verb' => 'POST'],
		['name' => 'SpeechToText#transcribeFile', 'url' => '/stt/transcribeFile', 'verb' => 'POST'],
	],
];
