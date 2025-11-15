<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'routes' => [
		['name' => 'config#getConfigValue', 'url' => '/config', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'assistant#getAssistantTaskResultPage', 'url' => '/task/view/{taskId}', 'verb' => 'GET'],
		['name' => 'assistant#getAssistantStandalonePage', 'url' => '/', 'verb' => 'GET'],

		['name' => 'preview#getFileImage', 'url' => '/preview', 'verb' => 'GET'],
	],
	'ocs' => [
		['name' => 'assistantApi#getAvailableTaskTypes', 'url' => '/api/{apiVersion}/task-types', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#getUserTasks', 'url' => '/api/{apiVersion}/tasks', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#parseTextFromFile', 'url' => '/api/{apiVersion}/parse-file', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#getNotifyWhenReady', 'url' => '/api/{apiVersion}/task/{ocpTaskId}/notify', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#notifyWhenReady', 'url' => '/api/{apiVersion}/task/{ocpTaskId}/notify', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#cancelNotifyWhenReady', 'url' => '/api/{apiVersion}/task/{ocpTaskId}/notify', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'assistantApi#uploadInputFile', 'url' => '/api/{apiVersion}/input-file', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#displayUserFile', 'url' => '/api/{apiVersion}/file/{fileId}/display', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#getUserFileInfo', 'url' => '/api/{apiVersion}/file/{fileId}/info', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#shareOutputFile', 'url' => '/api/{apiVersion}/task/{ocpTaskId}/file/{fileId}/share', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#saveOutputFile', 'url' => '/api/{apiVersion}/task/{ocpTaskId}/file/{fileId}/save', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'assistantApi#getOutputFilePreview', 'url' => '/api/{apiVersion}/task/{ocpTaskId}/output-file/{fileId}/preview', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#getOutputFile', 'url' => '/api/{apiVersion}/task/{ocpTaskId}/output-file/{fileId}/download', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'assistantApi#runFileAction', 'url' => '/api/{apiVersion}/file-action/{fileId}/{taskTypeId}', 'verb' => 'POST', 'requirements' => $requirements],

		['name' => 'chattyLLM#newSession', 'url' => '/chat/new_session', 'verb' => 'PUT'],
		['name' => 'chattyLLM#updateSessionTitle', 'url' => '/chat/update_session', 'verb' => 'PATCH'],
		['name' => 'chattyLLM#deleteSession', 'url' => '/chat/delete_session', 'verb' => 'DELETE'],
		['name' => 'chattyLLM#getSessions', 'url' => '/chat/sessions', 'verb' => 'GET'],
		['name' => 'chattyLLM#newMessage', 'url' => '/chat/new_message', 'verb' => 'PUT'],
		['name' => 'chattyLLM#deleteMessage', 'url' => '/chat/delete_message', 'verb' => 'DELETE'],
		['name' => 'chattyLLM#getMessages', 'url' => '/chat/messages', 'verb' => 'GET'],
		['name' => 'chattyLLM#getMessage', 'url' => '/chat/sessions/{sessionId}/messages/{messageId}', 'verb' => 'GET'],
		['name' => 'chattyLLM#generateForSession', 'url' => '/chat/generate', 'verb' => 'GET'],
		['name' => 'chattyLLM#regenerateForSession', 'url' => '/chat/regenerate', 'verb' => 'GET'],
		['name' => 'chattyLLM#checkSession', 'url' => '/chat/check_session', 'verb' => 'GET'],
		['name' => 'chattyLLM#checkMessageGenerationTask', 'url' => '/chat/check_generation', 'verb' => 'GET'],
		['name' => 'chattyLLM#streamGenerate', 'url' => '/chat/stream', 'verb' => 'GET'],
		['name' => 'chattyLLM#generateTitle', 'url' => '/chat/generate_title', 'verb' => 'GET'],
		['name' => 'chattyLLM#checkTitleGenerationTask', 'url' => '/chat/check_title_generation', 'verb' => 'GET'],
	],
];
