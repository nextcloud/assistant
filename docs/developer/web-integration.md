<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Integrating the assistant

This section is about integrating the assistant in Nextcloud's web frontend.

The assistant can be used anywhere in Nextcloud's frontend. The assistant modal can be opened with or without initial input.
It can also be opened to see the result of a task.

## Displaying a task result in the assistant

There are 2 ways to display a task with the assistant.

### Open the assistant modal

If you get a task with the `/ocs/v2.php/apps/assistant/api/v1/task/TASK_ID` or `/ocs/v2.php/apps/assistant/api/v1/tasks` OCS endpoint,
you can display it in the assistant UI by using the `OCA.Assistant.openAssistantTask` helper function.
This function takes the task object as parameter. Calling it will open the assistant and load the task values
(task type, input and output) in the UI.

### Browse the assistant task result page

There is a standalone page to display results of a task:
`/apps/assistant/task/view/TASK_ID`
It has the exact same content as the assistant modal.

## Run a task

A helper function is exposed as `OCA.Assistant.openAssistantForm`. It opens the assistant modal.

It accepts one parameter which is an object that can contain those keys:
* appId: [string, mandatory] app id of the app currently displayed
* customId: [string, optional, default: ''] the task custom ID (if the task is scheduled, this helps to identify the task when receiving the "task finished" event in the backend)
* taskType: [string, optional, default: last used task type] initially selected task type. It can be a text processing task type class or `speech-to-text` or `OCP\TextToImage\Task`
* input: [object, optional, default: {}] initial inputs (specific to each task type)
* isInsideViewer: [boolean, optional, default: false] should be true if this function is called while the Viewer is displayed
* closeOnResult: [boolean, optional, default: false] If true, the modal will be closed after running a synchronous task and getting its result
* actionButtons: [array, optional, default: empty list] List of extra buttons to show in the assistant result form (only used if closeOnResult is false)

This function returns a promise that resolves when the assistant is closed, which happens if:
* A task has been scheduled
* A task has run synchronously and we got results

The promise can also be rejected if something wrong happens.

The promise resolves with a task object which looks like:

```javascript
{
	appId: 'text',
	id: 310, // the assistant task ID
	customId: 'my custom identifier',
	input: { input: 'give me a short summary of a simple settings section about GitHub' },
	ocpTaskId: 152, // the underlying OCP task ID
	output: { output: 'blabla' },
	status: 'STATUS_SUCCESSFUL', // 0: unknown, 1: scheduled, 2: running, 3: sucessful, 4: failed
	type: 'core:text2text',
	lastUpdated: 1711545305,
	scheduledAt: 1711545301,
	startedAt: 1711545302,
	endedAt: 1711545303,
	userId: 'janedoe',
}
```

Complete example:
``` javascript
OCA.Assistant.openAssistantForm({
	appId: 'my_app_id',
	customId: 'my custom identifier',
	taskType: 'core:text2text',
	inputs: { input: 'count to 3' },
	actionButtons: [
		{
			label: 'Label 1',
			title: 'Title 1',
			variant: 'warning',
			iconSvg: cogSvg,
			onClick: (output) => { console.debug('first button clicked', output) },
		},
		{
			label: 'Label 2',
			title: 'Title 2',
			onClick: (output) => { console.debug('second button clicked', output) },
		},
	],
}).then(task => {
	console.debug('assistant promise success', task)
}).catch(error => {
	console.debug('assistant promise failure', error)
})
```

### Populate input fields with the content of a file

You might want to initialize an input field with the content of a file.
This is possible by passing a file path or ID like this:

``` javascript
OCA.Assistant.openAssistantForm({
	appId: 'my_app_id',
	customId: 'my custom identifier',
	taskType: 'core:text2text',
	inputs: { input: { fileId: 123 } },
})
OCA.Assistant.openAssistantForm({
	appId: 'my_app_id',
	customId: 'my custom identifier',
	taskType: 'core:text2text',
	inputs: { input: { filePath: '/path/to/file.txt' } },
})
```
