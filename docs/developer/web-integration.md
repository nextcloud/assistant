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
* identifier: [string, optional, default: ''] the task identifier (if the task is scheduled, this helps to identify the task when receiving the "task finished" event in the backend)
* taskType: [string, optional, default: last used task type] initially selected task type. It can be a text processing task type class or `speech-to-text` or `OCP\TextToImage\Task`
* input: [string, optional, default: ''] initial input prompt
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
	category: 1, // 0: text generation, 1: image generation, 2: speech-to-text
	id: 310, // the assistant task ID
	identifier: 'my custom identifier',
	inputs: { prompt: 'give me a short summary of a simple settings section about GitHub' },
	ocpTaskId: 152, // the underlying OCP task ID
	output: 'blabla',
	status: 3, // 0: unknown, 1: scheduled, 2: running, 3: sucessful, 4: failed
	taskType: 'OCP\\TextProcessing\\FreePromptTaskType',
	timestamp: 1711545305,
	userId: 'janedoe',
}
```

Complete example:
``` javascript
OCA.Assistant.openAssistantForm({
	appId: 'my_app_id',
	identifier: 'my custom identifier',
	taskType: 'OCP\\TextProcessing\\FreePromptTaskType',
	input: 'count to 3',
	actionButtons: [
		{
			label: 'Label 1',
			title: 'Title 1',
			type: 'warning',
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
