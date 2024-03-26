import { STATUS, TASK_CATEGORIES } from './constants.js'
import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('assistant', 'js/') // eslint-disable-line

export async function openAssistantTextProcessingForm(params) {
	return openAssistantForm(params)
}

// TODO add param to lock on specific task type

/**
 * Creates an assistant modal and return a promise which provides the result
 *
 * OCA.Assistant.openAssistantForm({
 *  appId: 'my_app_id',
 *  identifier: 'my task identifier',
 *  taskType: 'OCP\\TextProcessing\\FreePromptTaskType',
 *  input: 'count to 3',
 *  actionButtons: [
 *    {
 *      label: 'Label 1',
 *      title: 'Title 1',
 *      type: 'warning',
 *      iconSvg: cogSvg,
 *      onClick: (output) => { console.debug('first button clicked', output) },
 *    },
 *    {
 *      label: 'Label 2',
 *      title: 'Title 2',
 *      onClick: (output) => { console.debug('second button clicked', output) },
 *    },
 *  ],
 * }).then(r => {console.debug('scheduled task', r.data.ocs.data.task)})
 *
 * @param {object} params parameters for the assistant
 * @param {string} params.appId the scheduling app id
 * @param {string} params.identifier the task identifier
 * @param {string} params.taskType the text processing task type class
 * @param {string} params.input optional initial input text
 * @param {boolean} params.isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @param {boolean} params.closeOnResult If true, the modal will be closed when getting a sync result
 * @param {Array} params.actionButtons List of extra buttons to show in the assistant result form (only if closeOnResult is false)
 * @param {boolean} params.useMetaTasks If true, the promise will resolve with the meta task object instead of the ocp task
 * @return {Promise<unknown>}
 */
export async function openAssistantForm({
	appId, identifier = '', taskType = null, input = '',
	isInsideViewer = undefined, closeOnResult = false, actionButtons = undefined, useMetaTasks = false,
}) {
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	const { default: AssistantTextProcessingModal } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantTextProcessingModal.vue')
	Vue.mixin({ methods: { t, n } })

	// fallback to the last used one
	const selectedTaskTypeId = taskType ?? (await getLastSelectedTaskType())?.data

	return new Promise((resolve, reject) => {
		const modalId = 'assistantTextProcessingModal'
		const modalElement = document.createElement('div')
		modalElement.id = modalId
		document.body.append(modalElement)

		const View = Vue.extend(AssistantTextProcessingModal)
		const view = new View({
			propsData: {
				isInsideViewer,
				inputs: { prompt: input },
				selectedTaskTypeId,
				showScheduleConfirmation: false,
				showSyncTaskRunning: false,
				actionButtons,
			},
		}).$mount(modalElement)
		let lastTask = null

		view.$on('cancel', () => {
			view.$destroy()
			reject(new Error('User cancellation'))
		})
		view.$on('submit', (data) => {
			scheduleTask(appId, identifier, data.selectedTaskTypeId, data.inputs)
				.then(async (response) => {
					view.inputs = data.inputs
					view.showScheduleConfirmation = true
					const task = response.data?.ocs?.data?.task
					lastTask = task
					useMetaTasks ? resolve(task) : resolve(await resolveMetaTaskToOcpTask(task))
				})
				.catch(error => {
					view.$destroy()
					console.error('Assistant scheduling error', error)
					showError(t('assistant', 'Assistant error') + ': ' + error?.response?.data)
					reject(new Error('Assistant scheduling error'))
				})
		})
		const syncSubmit = (inputs, taskTypeId, newTaskIdentifier = '') => {
			view.loading = true
			view.showSyncTaskRunning = true
			view.inputs = inputs
			view.selectedTaskTypeId = taskTypeId
			if (taskTypeId === 'speech-to-text') {
				runSttTask(inputs).then(response => {
					view.showScheduleConfirmation = true
					view.loading = false
					view.showSyncTaskRunning = false
				})
				return
			}
			const runOrScheduleFunction = taskTypeId === 'OCP\\TextToImage\\Task'
				? runOrScheduleTtiTask
				: runOrScheduleTask
			runOrScheduleFunction(appId, newTaskIdentifier, taskTypeId, inputs)
				.then(async (response) => {
					const task = response.data?.ocs?.data?.task
					lastTask = task
					useMetaTasks ? resolve(task) : resolve(await resolveMetaTaskToOcpTask(task))
					view.inputs = task.inputs
					if (task.status === STATUS.successfull) {
						if (closeOnResult) {
							view.$destroy()
						} else {
							view.output = task?.output
						}
					} else if (task.status === STATUS.scheduled) {
						view.showScheduleConfirmation = true
					}
					view.loading = false
					view.showSyncTaskRunning = false
				})
				.catch(error => {
					if (error?.code === 'ERR_CANCELED') {
						view.output = ''
					} else {
						view.$destroy()
						console.error('Assistant sync run error', error)
						showError(t('assistant', 'Assistant error'))
						reject(new Error('Assistant sync run error'))
					}
				})
				.then(() => {
				})
		}
		view.$on('sync-submit', (data) => {
			syncSubmit(data.inputs, data.selectedTaskTypeId, identifier)
		})
		view.$on('try-again', (task) => {
			syncSubmit(task.inputs, task.taskType)
		})
		view.$on('load-task', (task) => {
			if (!view.loading) {
				view.selectedTaskTypeId = task.taskType
				view.inputs = task.inputs
				view.output = task.status === STATUS.successfull ? task.output : null
			}
		})
		view.$on('cancel-sync-n-schedule', () => {
			cancelCurrentSyncTask()
			const scheduleFunction = view.selectedTaskTypeId === 'OCP\\TextToImage\\Task'
				? scheduleTtiTask
				: scheduleTask
			scheduleFunction(appId, identifier, view.selectedTaskTypeId, view.inputs)
				.then(async (response) => {
					view.showSyncTaskRunning = false
					view.showScheduleConfirmation = true
					const task = response.data?.ocs?.data?.task
					lastTask = task
					useMetaTasks ? resolve(task) : resolve(await resolveMetaTaskToOcpTask(task))
				})
				.catch(error => {
					view.$destroy()
					console.error('Assistant scheduling error', error)
					showError(t('assistant', 'Assistant error'))
					reject(new Error('Assistant scheduling error'))
				})
		})
		view.$on('action-button-clicked', (data) => {
			if (data.button?.onClick) {
				lastTask.output = data.output
				data.button.onClick(lastTask)
			}
			view.$destroy()
		})
	})
}

export async function runSttTask(inputs) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType('speech-to-text')
	if (inputs.sttMode === 'choose') {
		const url = generateOcsUrl('/apps/assistant/api/v1/stt/transcribeFile')
		const params = { path: inputs.audioFilePath }
		return axios.post(url, params)
	} else {
		const url = generateOcsUrl('/apps/assistant/api/v1/stt/transcribeAudio')
		const formData = new FormData()
		formData.append('audioData', inputs.audioData)
		return axios.post(url, formData)
	}
}

export function scheduleTtiTask(appId, identifier, taskType, inputs) {
	return runOrScheduleTtiTask(appId, identifier, taskType, inputs, true)
}

export async function runOrScheduleTtiTask(appId, identifier, taskType, inputs, schedule = false) {
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType('OCP\\TextToImage\\Task')
	const params = {
		appId,
		identifier,
		prompt: inputs.prompt,
		nResults: inputs.nResults,
		displayPrompt: inputs.displayPrompt,
		notifyReadyIfScheduled: true,
		schedule,
	}
	const url = generateOcsUrl('/apps/assistant/api/v1/i/process_prompt')
	return axios.post(url, params, { signal: window.assistantAbortController.signal })
}

async function resolveMetaTaskToOcpTask(metaTask) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	if (metaTask.category !== TASK_CATEGORIES.text_generation) {
		// For now we only resolve text generation tasks
		return null
	}

	const url = generateOcsUrl('textprocessing/task/{taskId}', { taskId: metaTask.ocpTaskId })
	try {
		const response = await axios.get(url)
		console.debug('resolved meta task', response.data?.ocs?.data?.task)
		return response.data?.ocs?.data?.task
	} catch (error) {
		console.error(error)
		return null
	}
}

export async function cancelCurrentSyncTask() {
	window.assistantAbortController?.abort()
}

export async function runTask(appId, identifier, taskType, inputs) {
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	const url = generateOcsUrl('/apps/assistant/api/v1/task/run')
	const params = {
		inputs,
		type: taskType,
		appId,
		identifier,
	}
	return axios.post(url, params, { signal: window.assistantAbortController.signal })
}

export async function runOrScheduleTask(appId, identifier, taskType, inputs) {
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	const url = generateOcsUrl('/apps/assistant/api/v1/task/run-or-schedule')
	const params = {
		inputs,
		type: taskType,
		appId,
		identifier,
	}
	return axios.post(url, params, { signal: window.assistantAbortController.signal })
}

/**
 * Send a request to schedule a task
 *
 * @param {string} appId the scheduling app id
 * @param {string} identifier the task identifier
 * @param {string} taskType the task type class
 * @param {Array} inputs the task input texts as an array
 * @return {Promise<*>}
 */
export async function scheduleTask(appId, identifier, taskType, inputs) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	const url = generateOcsUrl('/apps/assistant/api/v1/task/schedule')
	const params = {
		inputs,
		type: taskType,
		appId,
		identifier,
	}
	return axios.post(url, params)
}

async function saveLastSelectedTaskType(taskType) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')

	const req = {
		values: {
			last_task_type: taskType,
		},
	}
	const url = generateUrl('/apps/assistant/config')
	return axios.put(url, req)
}

async function getLastSelectedTaskType() {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')

	const req = {
		params: {
			key: 'last_task_type',
		},
	}
	const url = generateUrl('/apps/assistant/config')
	return axios.get(url, req)
}

/**
 * Check if we want to cancel a notification action click and handle it ourselves
 *
 * @param {event} event the notification event
 */
export function handleNotification(event) {
	if (event.notification.app !== 'assistant' || event.action.type !== 'WEB') {
		return
	}
	// Handle the action click only if the task was scheduled by the assistant
	// or if the scheduling app didn't give any notification target
	// We use the object type to know
	if (event.notification.objectType === 'task') {
		event.cancelAction = true
		showAssistantTaskResult(event.notification.objectId)
	}
}

/**
 * Show the result of a task based on the meta task id
 *
 * @param {number} taskId the assistant meta task id to show the result of
 * @return {Promise<void>}
 */
async function showAssistantTaskResult(taskId) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-lazy" */'@nextcloud/router')
	const { showError } = await import(/* webpackChunkName: "dialogs-lazy" */'@nextcloud/dialogs')
	const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}', { taskId })
	axios.get(url).then(response => {
		console.debug('showing results for task', response.data?.ocs?.data?.task)
		openAssistantTaskResult(response.data?.ocs?.data?.task, true)
	}).catch(error => {
		console.error(error)
		showError(t('assistant', 'This task does not exist or has been cleaned up'))
	})
}

/**
 * Open an assistant modal to show a plain text result
 * @param {object} metaTask assistant meta task object
 * @return {Promise<void>}
 */
export async function openAssistantPlainTextResult(metaTask) {
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	const { default: AssistantPlainTextModal } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantPlainTextModal.vue')
	Vue.mixin({ methods: { t, n } })

	const modalId = 'assistantPlainTextModal'
	const modalElement = document.createElement('div')
	modalElement.id = modalId
	document.body.append(modalElement)

	const View = Vue.extend(AssistantPlainTextModal)
	const view = new View({
		propsData: {
			output: metaTask.output ?? '',
			taskCategory: metaTask.category,
		},
	}).$mount(modalElement)

	view.$on('cancel', () => {
		view.$destroy()
	})
}

/**
 * Open an assistant modal to show an image result
 * @param {object} metaTask assistant meta task object
 * @return {Promise<void>}
 */
export async function openAssistantImageResult(metaTask) {
	// For now just open the image generation result on a new page:
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-lazy" */'@nextcloud/router')
	const url = generateOcsUrl('/apps/assistant/api/v1/i/{genId}', { genId: metaTask.output })
	window.open(url, '_blank')
}

/**
 * Open an assistant modal to show the result of a task
 *
 * @param {object} task the task we want to see the result of
 * @param {boolean} useMetaTasks If false (default), treats the input task as an ocp task, otherwise as an assistant meta task
 * @return {Promise<void>}
 */
export async function openAssistantTaskResult(task, useMetaTasks = false) {
	// Divert to the right modal/page if we have a meta task with a category other than text generation:
	if (useMetaTasks) {
		switch (task.category) {
		/*
		case TASK_CATEGORIES.speech_to_text:
			openAssistantPlainTextResult(task)
			return

		case TASK_CATEGORIES.image_generation:
			openAssistantImageResult(task)
			return
		*/
		case TASK_CATEGORIES.text_generation:
		default:
			break
		}
	}

	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	Vue.mixin({ methods: { t, n } })
	const { showError } = await import(/* webpackChunkName: "dialogs-lazy" */'@nextcloud/dialogs')
	const { default: AssistantTextProcessingModal } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantTextProcessingModal.vue')

	const modalId = 'assistantTextProcessingModal'
	const modalElement = document.createElement('div')
	modalElement.id = modalId
	document.body.append(modalElement)

	const View = Vue.extend(AssistantTextProcessingModal)
	const view = new View({
		propsData: {
			// isInsideViewer,
			inputs: useMetaTasks ? task.inputs : [task.input],
			output: task.output ?? '',
			selectedTaskTypeId: useMetaTasks ? task.taskType : task.type,
			showScheduleConfirmation: false,
		},
	}).$mount(modalElement)

	view.$on('cancel', () => {
		view.$destroy()
	})
	view.$on('submit', (data) => {
		scheduleTask(task.appId, task.identifier ?? '', data.selectedTaskTypeId, data.inputs)
			.then((response) => {
				view.showScheduleConfirmation = true
				console.debug('scheduled task', response.data?.ocs?.data?.task)
			})
			.catch(error => {
				view.$destroy()
				console.error('Assistant scheduling error', error)
				showError(t('assistant', 'Failed to schedule the task'))
			})
	})
	const syncSubmit = (inputs, taskTypeId, newTaskIdentifier = '') => {
		view.loading = true
		view.showSyncTaskRunning = true
		view.inputs = inputs
		view.selectedTaskTypeId = taskTypeId
		if (taskTypeId === 'speech-to-text') {
			runSttTask(inputs).then(response => {
				view.showScheduleConfirmation = true
				view.loading = false
				view.showSyncTaskRunning = false
			})
			return
		}
		const runOrScheduleFunction = taskTypeId === 'OCP\\TextToImage\\Task'
			? runOrScheduleTtiTask
			: runOrScheduleTask
		runOrScheduleFunction(task.appId, newTaskIdentifier, taskTypeId, inputs)
			.then((response) => {
				// resolve(response.data?.ocs?.data?.task)
				const task = response.data?.ocs?.data?.task
				if (task.status === STATUS.successfull) {
					view.output = task?.output
				} else if (task.status === STATUS.scheduled) {
					view.inputs = task?.inputs
					view.showScheduleConfirmation = true
				}
				view.loading = false
				view.showSyncTaskRunning = false
			})
			.catch(error => {
				if (error?.code === 'ERR_CANCELED') {
					view.output = ''
				} else {
					view.$destroy()
					console.error('Assistant sync run error', error)
					// reject(new Error('Assistant sync run error'))
				}
			})
			.then(() => {
			})
	}
	view.$on('sync-submit', (data) => {
		syncSubmit(data.inputs, data.selectedTaskTypeId, task.identifier ?? '')
	})
	view.$on('try-again', (task) => {
		syncSubmit(task.inputs, task.taskType)
	})
	view.$on('load-task', (task) => {
		if (!view.loading) {
			view.selectedTaskTypeId = task.taskType
			view.inputs = task.inputs
			view.output = task.status === STATUS.successfull ? task.output : null
		}
	})
	view.$on('cancel-sync-n-schedule', () => {
		cancelCurrentSyncTask()
		const scheduleFunction = view.selectedTaskTypeId === 'OCP\\TextToImage\\Task'
			? scheduleTtiTask
			: scheduleTask
		scheduleFunction(task.appId, task.identifier ?? '', view.selectedTaskTypeId, view.inputs)
			.then((response) => {
				view.showSyncTaskRunning = false
				view.showScheduleConfirmation = true
				// resolve(response.data?.ocs?.data?.task)
			})
			.catch(error => {
				view.$destroy()
				console.error('Assistant scheduling error', error)
				// reject(new Error('Assistant scheduling error'))
			})
	})
}

export async function addAssistantMenuEntry() {
	const headerRight = document.querySelector('#header .header-right')
	const menuEntry = document.createElement('div')
	menuEntry.id = 'assistant'
	headerRight.prepend(menuEntry)

	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	const { default: AssistantHeaderMenuEntry } = await import(/* webpackChunkName: "assistant-header-lazy" */'./components/AssistantHeaderMenuEntry.vue')
	Vue.mixin({ methods: { t, n } })

	const View = Vue.extend(AssistantHeaderMenuEntry)
	const view = new View({
		propsData: {},
	}).$mount(menuEntry)

	view.$on('click', () => {
		openAssistantTextProcessingForm({ appId: 'assistant', useMetaTasks: true })
			.then(r => {
				console.debug('scheduled task', r)
			})
	})
}
