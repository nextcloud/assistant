/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { TASK_STATUS_STRING } from './constants.js'
import { showError } from '@nextcloud/dialogs'

window.assistantPollTimerId = null

// TODO add param to lock on specific task type

/**
 * Creates an assistant modal and return a promise which provides the result
 *
 * OCA.Assistant.openAssistantForm({
 *  appId: 'my_app_id',
 *  customId: 'my task custom ID',
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
 * @param {string} params.customId the task custom identifier
 * @param {string} params.identifier DEPRECATED the task custom identifier
 * @param {string} params.taskType the text processing task type class
 * @param {string} params.input DEPRECATED optional initial input text
 * @param {object} params.inputs optional initial named inputs
 * @param {boolean} params.isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @param {boolean} params.closeOnResult If true, the modal will be closed when getting a sync result
 * @param {Array} params.actionButtons List of extra buttons to show in the assistant result form (only if closeOnResult is false)
 * @param {HTMLElement} params.mountPoint The DOM element in which the assistant modal will be mounted
 * @return {Promise<unknown>}
 */
export async function openAssistantForm({
	appId, taskType = null, input = '', inputs = {},
	isInsideViewer = undefined, closeOnResult = false, actionButtons = undefined,
	customId = '', identifier = '', mountPoint = null,
}) {
	const { default: Vue } = await import('vue')
	const { default: AssistantTextProcessingModal } = await import('./components/AssistantTextProcessingModal.vue')
	Vue.mixin({ methods: { t, n } })

	// fallback to the last used one
	const selectedTaskTypeId = taskType ?? (await getLastSelectedTaskType())?.data

	return new Promise((resolve, reject) => {
		let modalMountPoint
		const content = document.querySelector('#content') ?? document.querySelector('#content-vue')

		if (mountPoint !== null) {
			// if a mount point is specified, always use it
			modalMountPoint = mountPoint
		} else {
			const modalId = 'assistantTextProcessingModal'
			modalMountPoint = document.createElement('div')
			modalMountPoint.id = modalId
			// the default mount point location is different whether the assistant is opened from the viewer or not
			if (isInsideViewer) {
				// so the assistant modal is opened on top of the current viewer
				document.querySelector('body').append(modalMountPoint)
			} else {
				// so the viewer can be later opened on top of the assistant
				document.querySelector('body').insertBefore(modalMountPoint, content.nextSibling)
			}
		}

		// TODO remaining issue: we can't open output files in the viewer if the assistant is displayed in the viewer
		// because the new viewer will replace the existing one...
		// Maybe that's an acceptable limitation

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
		}).$mount(modalMountPoint)
		let lastTask = null

		view.$on('cancel', () => {
			view.$destroy()
			reject(new Error('User cancellation'))
		})
		const syncSubmit = (inputs, taskTypeId, newTaskCustomId = '') => {
			view.loading = true
			view.showSyncTaskRunning = true
			view.progress = null
			view.expectedRuntime = null
			view.inputs = inputs
			view.selectedTaskTypeId = taskTypeId

			scheduleTask(appId, newTaskCustomId, taskTypeId, inputs)
				.then((response) => {
					const task = response.data?.ocs?.data?.task
					lastTask = task
					view.expectedRuntime = (lastTask?.completionExpectedAt - lastTask?.scheduledAt) || null
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
							showError(t('assistant', 'Your task with ID {id} has failed', { id: finishedTask.id }))
							console.error('[assistant] Task failed', finishedTask)
							view.outputs = null
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
			syncSubmit(data.inputs, data.selectedTaskTypeId, customId || identifier)
		})
		view.$on('try-again', (task) => {
			console.debug('[assistant] try again', task)
			syncSubmit(task.input, task.type)
		})
		view.$on('load-task', (task) => {
			if (!view.loading) {
				console.debug('[assistant] loading task', task)
				cancelTaskPolling()

				view.selectedTaskTypeId = task.type
				view.inputs = task.input
				view.outputs = task.status === TASK_STATUS_STRING.successful ? task.output : null
				view.selectedTaskId = task.id
				lastTask = task

				if ([TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(task?.status)) {
					getTask(task.id).then(response => {
						const updatedTask = response.data?.ocs?.data?.task

						if (![TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(updatedTask?.status)) {
							view.selectedTaskTypeId = updatedTask.type
							view.inputs = updatedTask.input
							view.outputs = updatedTask.status === TASK_STATUS_STRING.successful ? updatedTask.output : null
							view.selectedTaskId = updatedTask.id
							lastTask = updatedTask
							return
						}

						view.loading = true
						view.showSyncTaskRunning = true
						view.progress = null
						view.expectedRuntime = (updatedTask?.completionExpectedAt - updatedTask?.scheduledAt) || null

						const setProgress = (progress) => {
							view.progress = progress
						}
						pollTask(updatedTask.id, setProgress).then(finishedTask => {
							console.debug('pollTask.then', finishedTask)
							if (finishedTask.status === TASK_STATUS_STRING.successful) {
								view.outputs = finishedTask?.output
								view.selectedTaskId = finishedTask?.id
							} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
								showError(t('assistant', 'Your task with ID {id} has failed', { id: finishedTask.id }))
								console.error('[assistant] Task failed', finishedTask)
								view.outputs = null
							}
							// resolve(finishedTask)
							view.loading = false
							view.showSyncTaskRunning = false
						}).catch(error => {
							console.debug('[assistant] poll error', error)
						})
					}).catch(error => {
						console.error(error)
					})
				}
			}
		})
		view.$on('new-task', () => {
			console.debug('[assistant] new task')
			view.outputs = null
			view.selectedTaskId = null
			lastTask = null
		})
		view.$on('background-notify', () => {
			cancelTaskPolling()
			view.showScheduleConfirmation = true
			view.showSyncTaskRunning = false
			view.loading = false
			setNotifyReady(lastTask.id)
		})
		view.$on('cancel-task', () => {
			cancelTaskPolling()
			cancelTask(lastTask.id)
			view.showSyncTaskRunning = false
			view.loading = false
			lastTask = null
		})
		view.$on('action-button-clicked', (data) => {
			if (data.button?.onClick) {
				lastTask.output = data.output
				data.button.onClick(lastTask)
			}
			view.$destroy()
		})
		view.$on('back-to-assistant', () => {
			cancelTaskPolling()
			view.showScheduleConfirmation = false
			view.showSyncTaskRunning = false
			view.loading = false
			view.outputs = null
			lastTask = null
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
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	const url = generateOcsUrl('taskprocessing/task/{taskId}', { taskId })
	return axios.get(url, { signal: window.assistantAbortController.signal })
}

export async function setNotifyReady(taskId) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/notify', { taskId })
	return axios.post(url, {})
}

export async function cancelTask(taskId) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	const url = generateOcsUrl('taskprocessing/task/{taskId}', { taskId })
	return axios.delete(url, {})
}

/**
 * Send a request to schedule a task
 *
 * @param {string} appId the scheduling app id
 * @param {string} customId the task custom ID
 * @param {string} taskType the task type class
 * @param {Array} inputs the task input texts as an array
 * @return {Promise<*>}
 */
export async function scheduleTask(appId, customId, taskType, inputs) {
	window.assistantAbortController = new AbortController()
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	saveLastSelectedTaskType(taskType)
	if (taskType === 'core:text2text:translate') {
		saveLastTargetLanguage(inputs.target_language)
	}
	const url = generateOcsUrl('taskprocessing/schedule')
	const params = {
		input: inputs,
		type: taskType,
		appId,
		customId,
	}
	return axios.post(url, params, { signal: window.assistantAbortController.signal })
}

async function saveLastSelectedTaskType(taskType) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateUrl } = await import('@nextcloud/router')

	const req = {
		values: {
			last_task_type: taskType,
		},
	}
	const url = generateUrl('/apps/assistant/config')
	return axios.put(url, req)
}

async function getLastSelectedTaskType() {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateUrl } = await import('@nextcloud/router')

	const req = {
		params: {
			key: 'last_task_type',
		},
	}
	const url = generateUrl('/apps/assistant/config')
	return axios.get(url, req)
}

async function saveLastTargetLanguage(targetLanguage) {
	OCA.Assistant.last_target_language = targetLanguage

	const { default: axios } = await import('@nextcloud/axios')
	const { generateUrl } = await import('@nextcloud/router')

	const req = {
		values: {
			last_target_language: targetLanguage,
		},
	}
	const url = generateUrl('/apps/assistant/config')
	return axios.put(url, req)
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
		openAssistantTask(response.data?.ocs?.data?.task, {})
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
 * @param {object} params parameters for the assistant
 * @param {boolean} params.isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @param {Array} params.actionButtons List of extra buttons to show in the assistant result form
 * @param {HTMLElement} params.mountPoint The DOM element in which the assistant modal will be mounted
 * @return {Promise<void>}
 */
export async function openAssistantTask(
	task,
	{
		isInsideViewer = undefined,
		actionButtons = undefined,
		mountPoint = null,
	} = {}) {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { showError } = await import('@nextcloud/dialogs')
	const { default: AssistantTextProcessingModal } = await import('./components/AssistantTextProcessingModal.vue')

	let modalMountPoint
	const content = document.querySelector('#content') ?? document.querySelector('#content-vue')

	if (mountPoint !== null) {
		// if a mount point is specified, always use it
		modalMountPoint = mountPoint
	} else {
		const modalId = 'assistantTextProcessingModal'
		modalMountPoint = document.createElement('div')
		modalMountPoint.id = modalId
		// the default mount point location is different whether the assistant is opened from the viewer or not
		if (isInsideViewer) {
			// so the assistant modal is opened on top of the current viewer
			document.querySelector('body').append(modalMountPoint)
		} else {
			// so the viewer can be later opened on top of the assistant
			document.querySelector('body').insertBefore(modalMountPoint, content.nextSibling)
		}
	}

	const View = Vue.extend(AssistantTextProcessingModal)
	const view = new View({
		propsData: {
			isInsideViewer,
			selectedTaskId: task.id,
			inputs: task.input,
			outputs: task.output ?? {},
			selectedTaskTypeId: task.type,
			showScheduleConfirmation: false,
			actionButtons,
		},
	}).$mount(modalMountPoint)
	let lastTask = task

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
	const syncSubmit = (inputs, taskTypeId, newTaskCustomId = '') => {
		view.loading = true
		view.showSyncTaskRunning = true
		view.expectedRuntime = null
		view.inputs = inputs
		view.selectedTaskTypeId = taskTypeId

		scheduleTask('assistant', newTaskCustomId, taskTypeId, inputs)
			.then((response) => {
				const task = response.data?.ocs?.data?.task
				lastTask = task
				view.expectedRuntime = (lastTask?.completionExpectedAt - lastTask?.scheduledAt) || null
				pollTask(task.id).then(finishedTask => {
					if (finishedTask.status === TASK_STATUS_STRING.successful) {
						view.outputs = finishedTask?.output
						view.selectedTaskId = finishedTask?.id
					} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
						showError(t('assistant', 'Your task with ID {id} has failed', { id: finishedTask.id }))
						console.error('[assistant] Task failed', finishedTask)
						view.outputs = null
					}
					// resolve(finishedTask)
					view.loading = false
					view.showSyncTaskRunning = false
				}).catch(error => {
					console.debug('[assistant] poll error', error)
					view.outputs = null
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
		syncSubmit(task.input, task.type)
	})
	view.$on('load-task', (task) => {
		if (!view.loading) {
			cancelTaskPolling()

			view.selectedTaskTypeId = task.type
			view.inputs = task.input
			view.outputs = task.status === TASK_STATUS_STRING.successful ? task.output : null
			view.selectedTaskId = task.id
			lastTask = task

			if ([TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(task?.status)) {
				getTask(task.id).then(response => {
					const updatedTask = response.data?.ocs?.data?.task

					if (![TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(updatedTask?.status)) {
						view.selectedTaskTypeId = updatedTask.type
						view.inputs = updatedTask.input
						view.outputs = updatedTask.status === TASK_STATUS_STRING.successful ? updatedTask.output : null
						view.selectedTaskId = updatedTask.id
						lastTask = updatedTask
						return
					}

					view.loading = true
					view.showSyncTaskRunning = true
					view.progress = null
					view.expectedRuntime = (updatedTask?.completionExpectedAt - updatedTask?.scheduledAt) || null

					const setProgress = (progress) => {
						view.progress = progress
					}
					pollTask(updatedTask.id, setProgress).then(finishedTask => {
						console.debug('pollTask.then', finishedTask)
						if (finishedTask.status === TASK_STATUS_STRING.successful) {
							view.outputs = finishedTask?.output
							view.selectedTaskId = finishedTask?.id
						} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
							showError(t('assistant', 'Your task with ID {id} has failed', { id: finishedTask.id }))
							console.error('[assistant] Task failed', finishedTask)
							view.outputs = null
						}
						// resolve(finishedTask)
						view.loading = false
						view.showSyncTaskRunning = false
					}).catch(error => {
						console.debug('[assistant] poll error', error)
					})
				}).catch(error => {
					console.error(error)
				})
			}
		}
	})
	view.$on('new-task', () => {
		console.debug('[assistant] new task')
		view.outputs = null
		view.selectedTaskId = null
		lastTask = null
	})
	view.$on('background-notify', () => {
		cancelTaskPolling()
		view.showScheduleConfirmation = true
		view.showSyncTaskRunning = false
		setNotifyReady(lastTask.id)
	})
	view.$on('cancel-task', () => {
		cancelTaskPolling()
		cancelTask(lastTask.id)
		view.showSyncTaskRunning = false
		lastTask = null
	})
	view.$on('action-button-clicked', (data) => {
		if (data.button?.onClick) {
			lastTask.output = data.output
			data.button.onClick(lastTask)
		}
		view.$destroy()
	})
	view.$on('back-to-assistant', () => {
		cancelTaskPolling()
		view.showScheduleConfirmation = false
		view.showSyncTaskRunning = false
		view.loading = false
		view.outputs = null
		lastTask = null
	})
}

export async function addAssistantMenuEntry() {
	// changed in NC 31 header-right -> header-end
	const headerRight = document.querySelector('#header .header-right') ?? document.querySelector('#header .header-end')
	const menuEntry = document.createElement('div')
	menuEntry.id = 'assistant'
	headerRight.prepend(menuEntry)

	const { default: Vue } = await import('vue')
	const { default: AssistantHeaderMenuEntry } = await import('./components/AssistantHeaderMenuEntry.vue')
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
