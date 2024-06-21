import { TASK_STATUS_STRING } from './constants.js'
import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('assistant', 'js/') // eslint-disable-line
window.assistantPollTimerId = null

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
 *      onClick: (outputs) => { console.debug('first button clicked', outputs) },
 *    },
 *    {
 *      label: 'Label 2',
 *      title: 'Title 2',
 *      onClick: (outputs) => { console.debug('second button clicked', outputs) },
 *    },
 *  ],
 * }).then(r => {console.debug('scheduled task', r.data.ocs.data.task)})
 *
 * @param {object} params parameters for the assistant
 * @param {string} params.appId the scheduling app id
 * @param {string} params.identifier the task identifier
 * @param {string} params.taskType the text processing task type class
 * @param {string} params.input DEPRECATED optional initial input text
 * @param {object} params.inputs optional initial named inputs
 * @param {boolean} params.isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @param {boolean} params.closeOnResult If true, the modal will be closed when getting a sync result
 * @param {Array} params.actionButtons List of extra buttons to show in the assistant result form (only if closeOnResult is false)
 * @return {Promise<unknown>}
 */
export async function openAssistantForm({
	appId, identifier = '', taskType = null, input = '', inputs = {},
	isInsideViewer = undefined, closeOnResult = false, actionButtons = undefined,
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
				inputs: input ? { prompt: input } : inputs,
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
		const syncSubmit = (inputs, taskTypeId, newTaskIdentifier = '') => {
			view.loading = true
			view.showSyncTaskRunning = true
			view.progress = null
			view.inputs = inputs
			view.selectedTaskTypeId = taskTypeId

			scheduleTask(appId, newTaskIdentifier, taskTypeId, inputs)
				.then((response) => {
					const task = response.data?.ocs?.data?.task
					lastTask = task
					const setProgress = (progress) => {
						view.progress = progress
					}
					pollTask(task.id, setProgress).then(finishedTask => {
						console.debug('pollTask.then', finishedTask)
						if (finishedTask.status === TASK_STATUS_STRING.successful) {
							if (closeOnResult) {
								view.$destroy()
							} else {
								view.outputs = finishedTask?.output
								view.selectedTaskId = finishedTask?.id
							}
						} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
							showError(t('assistant', 'Your task has failed'))
							console.error('[assistant] Task failed', finishedTask)
						}
						resolve(finishedTask)
						view.loading = false
						view.showSyncTaskRunning = false
					}).catch(error => {
						console.debug('[assistant] poll error', error)
					})
				})
				.catch(error => {
					view.$destroy()
					console.error('Assistant scheduling error', error)
					showError(t('assistant', 'Assistant error') + ': ' + error?.response?.data)
					reject(new Error('Assistant scheduling error'))
				})
		}
		view.$on('sync-submit', (data) => {
			console.debug('[assistant] submit', data)
			syncSubmit(data.inputs, data.selectedTaskTypeId, identifier)
		})
		view.$on('try-again', (task) => {
			syncSubmit(task.input, task.taskType)
		})
		view.$on('load-task', (task) => {
			if (!view.loading) {
				console.debug('aaaaa loading task', task)
				view.selectedTaskTypeId = task.taskType
				view.inputs = task.input
				view.outputs = task.status === TASK_STATUS_STRING.successful ? task.output : null
				view.selectedTaskId = task.id
				lastTask = task
			}
		})
		view.$on('cancel-sync-n-schedule', () => {
			cancelTaskPolling()
			view.showScheduleConfirmation = true
			view.showSyncTaskRunning = false
			setNotifyReady(lastTask.id)
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

export async function pollTask(taskId, setProgress) {
	return new Promise((resolve, reject) => {
		window.assistantPollTimerId = setInterval(() => {
			getTask(taskId).then(response => {
				const task = response.data?.ocs?.data?.task
				if (window.assistantPollTimerId === null) {
					reject(new Error('pollTask cancelled'))
					return
				}
				if (task?.status === TASK_STATUS_STRING.running) {
					setProgress(task?.progress * 100)
				}
				if (![TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(task?.status)) {
					// stop polling
					clearInterval(window.assistantPollTimerId)
					window.assistantPollTimerId = null
					resolve(task)
				}
			}).catch(error => {
				console.debug('[assistant] poll request failed', error)
				reject(new Error('pollTask request failed'))
			})
		}, 2000)
	})
}

export async function cancelTaskPolling() {
	window.assistantAbortController?.abort()
	clearInterval(window.assistantPollTimerId)
	window.assistantPollTimerId = null
}

export async function getTask(taskId) {
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	const url = generateOcsUrl('taskprocessing/task/{taskId}', { taskId })
	return axios.get(url, { signal: window.assistantAbortController.signal })
}

export async function setNotifyReady(taskId) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/notify', { taskId })
	return axios.post(url, {})
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
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	const url = generateOcsUrl('taskprocessing/schedule')
	const params = {
		input: inputs,
		type: taskType,
		appId,
		identifier,
	}
	return axios.post(url, params, { signal: window.assistantAbortController.signal })
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
	getTask(taskId).then(response => {
		console.debug('showing results for task', response.data?.ocs?.data?.task)
		openAssistantTask(response.data?.ocs?.data?.task)
	}).catch(error => {
		if (error.response?.status === 401) {
			showError(t('assistant', 'Please log in to view the task result'))
			return
		}

		console.error(error)
		showError(t('assistant', 'This task does not exist or has been cleaned up'))
	})
}

/**
 * Open an assistant modal to show the result of a task
 *
 * @param {object} task the task we want to see the result of
 * @return {Promise<void>}
 */
export async function openAssistantTask(task) {
	console.debug('ZERO')
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	Vue.mixin({ methods: { t, n } })
	const { showError } = await import(/* webpackChunkName: "dialogs-lazy" */'@nextcloud/dialogs')
	const { default: AssistantTextProcessingModal } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantTextProcessingModal.vue')

	const modalId = 'assistantTextProcessingModal'
	const modalElement = document.createElement('div')
	modalElement.id = modalId
	document.body.append(modalElement)

	console.debug('ONE')
	const View = Vue.extend(AssistantTextProcessingModal)
	const view = new View({
		propsData: {
			// isInsideViewer,
			selectedTaskId: task.id,
			inputs: task.input,
			outputs: task.output ?? {},
			selectedTaskTypeId: task.type,
			showScheduleConfirmation: false,
		},
	}).$mount(modalElement)
	let lastTask = null
	console.debug('TWO')

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

		scheduleTask('assistant', newTaskIdentifier, taskTypeId, inputs)
			.then((response) => {
				const task = response.data?.ocs?.data?.task
				lastTask = task
				pollTask(task.id).then(finishedTask => {
					if (finishedTask.status === TASK_STATUS_STRING.successful) {
						view.outputs = finishedTask?.output
						view.selectedTaskId = finishedTask?.id
					}
					// resolve(finishedTask)
					view.loading = false
					view.showSyncTaskRunning = false
				}).catch(error => {
					console.debug('[assistant] poll error', error)
				})
			})
			.catch(error => {
				view.$destroy()
				console.error('Assistant scheduling error', error)
				showError(t('assistant', 'Assistant error') + ': ' + error?.response?.data)
				// reject(new Error('Assistant scheduling error'))
			})
	}
	view.$on('sync-submit', (data) => {
		syncSubmit(data.inputs, data.selectedTaskTypeId, task.identifier ?? '')
	})
	view.$on('try-again', (task) => {
		syncSubmit(task.input, task.taskType)
	})
	view.$on('load-task', (task) => {
		if (!view.loading) {
			view.selectedTaskTypeId = task.taskType
			view.inputs = task.input
			view.outputs = task.status === TASK_STATUS_STRING.successful ? task.output : null
			view.selectedTaskId = task.id
		}
	})
	view.$on('cancel-sync-n-schedule', () => {
		cancelTaskPolling()
		view.showScheduleConfirmation = true
		view.showSyncTaskRunning = false
		setNotifyReady(lastTask.id)
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
		openAssistantForm({ appId: 'assistant' })
			.then(r => {
				console.debug('scheduled task', r)
			})
	})
}
