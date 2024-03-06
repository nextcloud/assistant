<?php

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'routes' => [
		['name' => 'config#getConfigValue', 'url' => '/config', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'assistant#getAssistantTaskResultPage', 'url' => '/task/view/{metaTaskId}', 'verb' => 'GET'],

		['name' => 'Text2Image#showGenerationPage', 'url' => '/i/{imageGenId}', 'verb' => 'GET'],

		['name' => 'FreePrompt#processPrompt', 'url' => '/f/process_prompt', 'verb' => 'POST'],
		['name' => 'FreePrompt#getPromptHistory', 'url' => '/f/prompt_history', 'verb' => 'GET'],
		['name' => 'FreePrompt#getOutputs', 'url' => '/f/get_outputs', 'verb' => 'GET'],
		['name' => 'FreePrompt#cancelGeneration', 'url' => '/f/cancel_generation', 'verb' => 'POST'],

		['name' => 'SpeechToText#getResultPage', 'url' => '/stt/result-page/{metaTaskId}', 'verb' => 'GET'],
	],
	'ocs' => [
		['name' => 'assistantApi#getAvailableTaskTypes', 'url' => '/api/{apiVersion}/task-types', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#getAssistantTask', 'url' => '/api/{apiVersion}/task/{metaTaskId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#getUserTasks', 'url' => '/api/{apiVersion}/tasks', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#runTextProcessingTask', 'url' => '/api/{apiVersion}/task/run', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#scheduleTextProcessingTask', 'url' => '/api/{apiVersion}/task/schedule', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#runOrScheduleTextProcessingTask', 'url' => '/api/{apiVersion}/task/run-or-schedule', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#parseTextFromFile', 'url' => '/api/{apiVersion}/parse-file', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#deleteTask', 'url' => '/api/{apiVersion}/task/{metaTaskId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'assistantApi#cancelTask', 'url' => '/api/{apiVersion}/task/cancel/{metaTaskId}', 'verb' => 'PUT', 'requirements' => $requirements],

		['name' => 'Text2ImageApi#processPrompt', 'url' => '/api/{apiVersion}/i/process_prompt', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'Text2ImageApi#getPromptHistory', 'url' => '/api/{apiVersion}/i/prompt_history', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Text2ImageApi#getGenerationInfo', 'url' => '/api/{apiVersion}/i/info/{imageGenId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Text2ImageApi#getImage', 'url' => '/api/{apiVersion}/i/{imageGenId}/{fileNameId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'Text2ImageApi#cancelGeneration', 'url' => '/api/{apiVersion}/i/cancel_generation', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'Text2ImageApi#setVisibilityOfImageFiles', 'url' => '/api/{apiVersion}/i/visibility/{imageGenId}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'Text2ImageApi#notifyWhenReady', 'url' => '/api/{apiVersion}/i/notify/{imageGenId}', 'verb' => 'POST', 'requirements' => $requirements],

		['name' => 'SpeechToTextApi#transcribeAudio', 'url' => '/api/{apiVersion}/stt/transcribeAudio', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'SpeechToTextApi#transcribeFile', 'url' => '/api/{apiVersion}/stt/transcribeFile', 'verb' => 'POST', 'requirements' => $requirements],
	],
];
