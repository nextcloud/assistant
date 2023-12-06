import { STATUS } from './constants.js'
import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('assistant', 'js/') // eslint-disable-line

/**
 * Creates an assistant modal and return a promise which provides the result
 *
 * OCA.TPAssistant.openAssistantForm({
 *  appId: 'my_app_id',
 *  identifier: 'my task identifier',
 *  taskType: 'OCP\\TextProcessing\\FreePromptTaskType',
 *  input: 'count to 3'
 * }).then(r => {console.debug('scheduled task', r.data.ocs.data.task)})
 *
 * @param {object} params parameters for the assistant
 * @param {string} params.appId the scheduling app id
 * @param {string} params.identifier the task identifier
 * @param {string} params.taskType the task type class
 * @param {string} params.input optional initial input text
 * @param {boolean} params.isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @param {boolean} params.closeOnResult If true, the modal will be closed when getting a sync result
 * @return {Promise<unknown>}
 */
export async function openAssistantForm({
	appId, identifier = '', taskType = null, input = '',
	isInsideViewer = undefined, closeOnResult = false,
}) {
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	const { default: AssistantModal } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantModal.vue')
	Vue.mixin({ methods: { t, n } })

	// fallback to the last used one
	const selectedTaskTypeId = taskType ?? (await getLastSelectedTaskType())?.data

	return new Promise((resolve, reject) => {
		const modalId = 'assistantModal'
		const modalElement = document.createElement('div')
		modalElement.id = modalId
		document.body.append(modalElement)

		const View = Vue.extend(AssistantModal)
		const view = new View({
			propsData: {
				isInsideViewer,
				input,
				selectedTaskTypeId,
				showScheduleConfirmation: false,
				showSyncTaskRunning: false,
			},
		}).$mount(modalElement)

		view.$on('cancel', () => {
			view.$destroy()
			reject(new Error('User cancellation'))
		})
		view.$on('submit', (data) => {
			scheduleTask(appId, identifier, data.taskTypeId, data.input)
				.then((response) => {
					view.input = data.input
					view.showScheduleConfirmation = true
					resolve(response.data?.ocs?.data?.task)
				})
				.catch(error => {
					view.$destroy()
					console.error('Assistant scheduling error', error)
					reject(new Error('Assistant scheduling error'))
				})
		})
		view.$on('sync-submit', (data) => {
			view.loading = true
			view.showSyncTaskRunning = true
			view.input = data.input
			view.selectedTaskTypeId = data.taskTypeId
			runOrScheduleTask(appId, identifier, data.taskTypeId, data.input)
				.then((response) => {
					const task = response.data?.task
					resolve(task)
					if (task.status === STATUS.successfull) {
						if (closeOnResult) {
							view.$destroy()
						} else {
							view.output = task?.output
						}
					} else if (task.status === STATUS.scheduled) {
						view.input = task.input
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
						reject(new Error('Assistant sync run error'))
					}
				})
				.then(() => {
				})
		})
		view.$on('cancel-sync-n-schedule', () => {
			cancelCurrentSyncTask()
			scheduleTask(appId, identifier, view.selectedTaskTypeId, view.input)
				.then((response) => {
					view.showSyncTaskRunning = false
					view.showScheduleConfirmation = true
					resolve(response.data?.ocs?.data?.task)
				})
				.catch(error => {
					view.$destroy()
					console.error('Assistant scheduling error', error)
					reject(new Error('Assistant scheduling error'))
				})
		})
	})
}

export async function cancelCurrentSyncTask() {
	window.assistantAbortController?.abort()
}

export async function runTask(appId, identifier, taskType, input) {
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	const url = generateUrl('/apps/assistant/run')
	const params = {
		input,
		type: taskType,
		appId,
		identifier,
	}
	return axios.post(url, params, { signal: window.assistantAbortController.signal })
}

export async function runOrScheduleTask(appId, identifier, taskType, input) {
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateUrl } = await import(/* webpackChunkName: "router-gen-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	const url = generateUrl('/apps/assistant/run-or-schedule')
	const params = {
		input,
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
 * @param {string} input the task input text
 * @return {Promise<*>}
 */
export async function scheduleTask(appId, identifier, taskType, input) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-genocs-lazy" */'@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	const url = generateOcsUrl('textprocessing/schedule', 2)
	const params = {
		input,
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
		showResults(event.notification.objectId)
	}
}

/**
 * Show the result of a task
 *
 * @param {number} taskId the task id to show the result of
 * @return {Promise<void>}
 */
async function showResults(taskId) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-lazy" */'@nextcloud/router')
	const { showError } = await import(/* webpackChunkName: "dialogs-lazy" */'@nextcloud/dialogs')
	const url = generateOcsUrl('textprocessing/task/{taskId}', { taskId })
	axios.get(url).then(response => {
		console.debug('showing results for task', response.data.ocs.data.task)
		openAssistantResult(response.data.ocs.data.task)
	}).catch(error => {
		console.error(error)
		showError(t('assistant', 'This task does not exist or has been cleaned up'))
	})
}

/**
 * Open an assistant modal to show  the result of a task
 *
 * @param {object} task the task we want to see the result of
 * @return {Promise<void>}
 */
export async function openAssistantResult(task) {
	const { showError } = await import(/* webpackChunkName: "dialogs-lazy" */'@nextcloud/dialogs')
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	const { default: AssistantModal } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantModal.vue')
	Vue.mixin({ methods: { t, n } })

	const modalId = 'assistantModal'
	const modalElement = document.createElement('div')
	modalElement.id = modalId
	document.body.append(modalElement)

	const View = Vue.extend(AssistantModal)
	const view = new View({
		propsData: {
			// isInsideViewer,
			input: task.input,
			output: task.output ?? '',
			selectedTaskTypeId: task.type,
			showScheduleConfirmation: false,
		},
	}).$mount(modalElement)

	view.$on('cancel', () => {
		view.$destroy()
	})
	view.$on('submit', (data) => {
		scheduleTask(task.appId, task.identifier, data.taskTypeId, data.input)
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
	view.$on('sync-submit', (data) => {
		view.loading = true
		view.showSyncTaskRunning = true
		view.input = data.input
		view.selectedTaskTypeId = data.taskTypeId
		runTask(task.appId, task.identifier, data.taskTypeId, data.input)
			.then((response) => {
				// resolve(response.data?.task)
				const task = response.data?.task
				if (task.status === STATUS.successfull) {
					view.output = task?.output
				} else if (task.status === STATUS.scheduled) {
					view.input = task?.input
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
	})
	view.$on('cancel-sync-n-schedule', () => {
		cancelCurrentSyncTask()
		scheduleTask(task.appId, task.identifier, view.selectedTaskTypeId, view.input)
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
		openAssistantForm({ appId: 'assistant' })
			.then(r => {
				console.debug('scheduled task', r)
			})
	})
}
