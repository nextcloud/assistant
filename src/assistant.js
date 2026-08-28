/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { TASK_STATUS_STRING, TASK_STATUS_INT } from './constants.js'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import { listen } from '@nextcloud/notify_push'

export class TaskPollCancelledError extends Error {
	constructor(msg = 'pollTask cancelled') {
		super(msg)
		this.name = 'TaskPollCancelledError'
	}
}

window.assistantPollAbortController = null
window.assistantPollTimerId = null
window.assistantPollRetryTimerId = null
window.assistantPollTaskId = null
window.assistantPollPositionTimerId = null
window.assistantPollPositionRetryTimerId = null
window.assistantPollPositionTaskId = null
window.assistantPollPositionAbortController = null
window.assistantSchedulingAbortController = null

listen('taskprocessing:task_update', (type, body) => {
	console.debug('[assistant] received task update push notification', type, body)
	const newStatus = body.new_status
	const taskId = body.task_id
	if (newStatus === TASK_STATUS_INT.successful) {
		// when a task successfully finished, we want to update its status AND output
		// in case it is not the currently selected task
		getTask(taskId).then(response => {
			const task = response.data?.ocs?.data?.task
			emit('assistant:task:updated', task)
		})
	} else {
		emit('assistant:task:status:updated', { taskId, status: newStatus })
	}
})

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
 *      variant: 'warning',
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
 * @param {string} params.taskType the selected task type ID
 * @param {Array} params.taskTypeIdList the task types to display (all if not specified)
 * @param {string} params.input DEPRECATED optional initial input text
 * @param {object} params.inputs optional initial named inputs
 * @param {boolean} params.isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @param {boolean} params.closeOnResult If true, the modal will be closed when getting a sync result
 * @param {Array} params.actionButtons List of extra buttons to show in the assistant result form (only if closeOnResult is false)
 * @param {HTMLElement} params.mountPoint The DOM element in which the assistant modal will be mounted
 * @return {Promise<unknown>}
 */
export async function openAssistantForm({
	appId, taskType = null, taskTypeIdList = null, input = '', inputs = {},
	isInsideViewer = undefined, closeOnResult = false, actionButtons = undefined,
	customId = '', identifier = '', mountPoint = null,
}) {
	const { createApp } = await import('vue')
	const { default: AssistantTextProcessingModal } = await import('./components/AssistantTextProcessingModal.vue')

	// fallback to the last used one
	const selectedTaskTypeId = taskType ?? (await getLastSelectedTaskType())?.data

	return new Promise((resolve, reject) => {
		if (OCA.Assistant.isAssistantDialogOpen) {
			reject(new Error('Assistant dialog is already open'))
			return
		}
		OCA.Assistant.isAssistantDialogOpen = true

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
				modalMountPoint.classList.add('insideViewer')
			} else {
				// so the viewer can be later opened on top of the assistant
				document.querySelector('body').insertBefore(modalMountPoint, content.nextSibling)
			}
		}

		// TODO remaining issue: we can't open output files in the viewer if the assistant is displayed in the viewer
		// because the new viewer will replace the existing one...
		// Maybe that's an acceptable limitation

		const app = createApp(
			AssistantTextProcessingModal,
			{
				isInsideViewer,
				initInputs: input ? { prompt: input } : inputs,
				initSelectedTaskTypeId: selectedTaskTypeId,
				showSyncTaskRunning: false,
				actionButtons,
				taskTypeIdList,
				/*
				// events emitted by the root component can be listened to this way
				// this is a handler for the 'load-task' event
				onLoadTask(data) {
				},
				*/
			},
		)
		app.mixin({ methods: { t, n } })
		app.use(PrimeVue, {
			theme: {
				preset: Aura,
				options: {
					prefix: 'p',
					darkModeSelector: 'system',
					cssLayer: false,
				},
			},
		})
		const view = app.mount(modalMountPoint)
		let lastTask = null

		// notify push stuff
		const isListeningTo = {}
		// listen only if needed
		// return true if notify_push is available
		// we can't cleanup isListeningTo because there is no way to remove a handler with @nextcloud/notify_push
		// TODO cleanup the handlers when we know we don't wanna listen anymore to a channel (task finished, failed...)
		const listenToTaskNotifications = (pushTaskId) => {
			if (isListeningTo[pushTaskId]) {
				return true
			}
			// attempt to listen to push notifications to get the intermediate output
			const pushChannel = 'taskprocessing:task_id_' + pushTaskId
			const hasPush = listen(pushChannel, (type, body) => {
				console.debug('[assistant] received push notification', type, body)
				if (pushTaskId === view.selectedTaskId) {
					view.outputs = body ?? null
				} else {
					console.debug('[assistant] ignoring push notification for task', pushTaskId, 'the selected one is', view.selectedTaskId)
				}
			})
			if (hasPush) {
				isListeningTo[pushTaskId] = true
			}
			return hasPush
		}

		modalMountPoint.addEventListener('cancel', () => {
			cancelScheduling()
			cancelTaskPolling()
			cancelTaskPositionPolling()
			app.unmount()
			OCA.Assistant.isAssistantDialogOpen = false
			reject(new Error('User cancellation'))
		})
		const syncSubmit = (inputs, taskTypeId, newTaskCustomId = '') => {
			cancelScheduling()
			view.loading = true
			view.showSyncTaskRunning = true
			view.taskPosition = null
			view.isNotifyEnabled = false
			view.progress = null
			view.expectedRuntime = null
			view.startedAt = null
			view.completionExpectedAt = null
			view.inputs = inputs
			view.outputs = null
			view.selectedTaskTypeId = taskTypeId

			const controller = new AbortController()
			window.assistantSchedulingAbortController = controller
			scheduleTask(appId, newTaskCustomId, taskTypeId, inputs, controller.signal)
				.then((response) => {
					if (window.assistantSchedulingAbortController !== controller) {
						return
					}
					cancelScheduling()
					const task = response.data?.ocs?.data?.task
					lastTask = task
					view.selectedTaskId = lastTask?.id
					view.expectedRuntime = (lastTask?.completionExpectedAt - lastTask?.scheduledAt) || null
					view.startedAt = lastTask?.startedAt || null
					view.completionExpectedAt = lastTask?.completionExpectedAt || null
					const hasPush = listenToTaskNotifications(task.id)
					console.debug('[assistant] HAS PUSH', hasPush)

					pollTaskPosition(task.id, view).then(() => {
						console.debug('[assistant] pollTaskPosition: the task is not scheduled anymore ', task.id)
					}).catch(error => {
						if (error instanceof TaskPollCancelledError) {
							return
						}
						console.debug('[assistant] pollPosition error', task.id, error.message)
					})
					// no need to update the task output with polling if we have push notifications
					pollTask(task.id, view, !hasPush).then(finishedTask => {
						console.debug('pollTask.then', finishedTask)
						if (finishedTask.status === TASK_STATUS_STRING.successful) {
							if (closeOnResult) {
								app.unmount()
								OCA.Assistant.isAssistantDialogOpen = false
							} else {
								view.outputs = finishedTask?.output
							}
						} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
							if (finishedTask.userFacingErrorMessage) {
								showError(
									t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
									+ ': ' + finishedTask.userFacingErrorMessage,
								)
							} else {
								showError(
									t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
									+ '. ' + t('assistant', 'Please inform the server administrators of this issue.'),
								)
							}
							console.error('[assistant] Task failed', finishedTask)
							view.outputs = null
						}
						resolve(finishedTask)
						view.loading = false
						view.showSyncTaskRunning = false
						view.taskPosition = null
						cancelTaskPositionPolling()
						emit('assistant:task:updated', finishedTask)
					}).catch(error => {
						if (error instanceof TaskPollCancelledError) {
							return
						}
						console.debug('[assistant] poll error', error.message)
						view.taskPosition = null
						cancelTaskPositionPolling()
						if (error.message === 'task-not-found') {
							view.loading = false
							view.showSyncTaskRunning = false
							view.isNotifyEnabled = false
							view.outputs = null
							view.selectedTaskId = null
							lastTask = null
							showError(t('assistant', 'The current Assistant task could not be found'))
						}
					})
				})
				.catch(error => {
					if (controller.signal.aborted) {
						return
					}
					if (window.assistantSchedulingAbortController === controller) {
						cancelScheduling()
					}
					view.loading = false
					view.showSyncTaskRunning = false
					view.taskPosition = null
					console.error('Assistant scheduling error', error?.response?.data?.ocs?.data?.message)
					showError(t('assistant', 'Assistant error') + ': ' + t('assistant', 'Something went wrong when scheduling the task'))
				})
		}
		modalMountPoint.addEventListener('sync-submit', (data) => {
			console.debug('[assistant] submit', data)
			syncSubmit(data.detail.inputs, data.detail.selectedTaskTypeId, customId || identifier)
		})
		modalMountPoint.addEventListener('try-again', (data) => {
			cancelScheduling()
			const task = data.detail
			console.debug('[assistant] try again', task)
			syncSubmit(task.input, task.type)
		})
		modalMountPoint.addEventListener('load-task', (data) => {
			const task = data.detail
			console.debug('[assistant] loading task', task)
			cancelScheduling()
			cancelTaskPolling()
			cancelTaskPositionPolling()
			view.showSyncTaskRunning = false
			view.taskPosition = null
			view.isNotifyEnabled = false
			view.loading = false
			view.taskStatus = task.status

			view.selectedTaskTypeId = task.type
			view.inputs = task.input
			view.outputs = task.status === TASK_STATUS_STRING.successful ? task.output : null
			view.selectedTaskId = task.id
			lastTask = task

			if ([TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(task?.status)) {
				getTask(task.id).then(response => {
					if (task.id !== view.selectedTaskId) {
						console.debug('[assistant] ignoring stale getTask response for task', task.id, 'selected is', view.selectedTaskId)
						return
					}
					const updatedTask = response.data?.ocs?.data?.task

					if (![TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(updatedTask?.status)) {
						view.selectedTaskTypeId = updatedTask.type
						view.inputs = updatedTask.input
						view.outputs = updatedTask.status === TASK_STATUS_STRING.successful ? updatedTask.output : null
						view.selectedTaskId = updatedTask.id
						view.taskStatus = updatedTask.status
						lastTask = updatedTask
						return
					}

					getNotifyReady(task.id).then(response => {
						if (task.id !== view.selectedTaskId) {
							return
						}
						view.isNotifyEnabled = !!response.data?.ocs?.data?.id
					}).catch(error => {
						console.error('[assistant] get task notification status error', error)
					})

					view.loading = true
					view.showSyncTaskRunning = true
					view.taskPosition = null
					view.progress = null
					view.expectedRuntime = (updatedTask?.completionExpectedAt - updatedTask?.scheduledAt) || null
					view.startedAt = lastTask?.startedAt || null
					view.completionExpectedAt = lastTask?.completionExpectedAt || null

					const hasPush = listenToTaskNotifications(task.id)
					console.debug('[assistant] HAS PUSH', hasPush)

					pollTaskPosition(updatedTask.id, view).then(() => {
						console.debug('[assistant] pollTaskPosition: the task is not scheduled anymore', updatedTask.id)
					}).catch(error => {
						if (error instanceof TaskPollCancelledError) {
							return
						}
						console.debug('[assistant] pollPosition error', updatedTask.id, error.message)
					})
					pollTask(updatedTask.id, view, !hasPush).then(finishedTask => {
						console.debug('pollTask.then', finishedTask)
						if (finishedTask.status === TASK_STATUS_STRING.successful) {
							view.outputs = finishedTask?.output
							view.selectedTaskId = finishedTask?.id
						} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
							if (finishedTask.userFacingErrorMessage) {
								showError(
									t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
									+ ': ' + finishedTask.userFacingErrorMessage,
								)
							} else {
								showError(
									t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
									+ '. ' + t('assistant', 'Please inform the server administrators of this issue.'),
								)
							}
							console.error('[assistant] Task failed', finishedTask)
							view.outputs = null
						}
						// resolve(finishedTask)
						view.loading = false
						view.showSyncTaskRunning = false
						view.taskPosition = null
						cancelTaskPositionPolling()
						emit('assistant:task:updated', finishedTask)
					}).catch(error => {
						if (error instanceof TaskPollCancelledError) {
							return
						}
						console.debug('[assistant] poll error', error)
						view.taskPosition = null
						cancelTaskPositionPolling()
						if (error.message === 'task-not-found') {
							view.loading = false
							view.showSyncTaskRunning = false
							view.isNotifyEnabled = false
							view.outputs = null
							view.selectedTaskId = null
							lastTask = null
							showError(t('assistant', 'The current Assistant task could not be found'))
						}
					})
				}).catch(error => {
					console.error(error)
				})
			}
		})
		modalMountPoint.addEventListener('new-task', () => {
			console.debug('[assistant] new task')
			cancelScheduling()
			cancelTaskPolling()
			cancelTaskPositionPolling()
			view.loading = false
			view.showSyncTaskRunning = false
			view.taskPosition = null
			view.isNotifyEnabled = false
			view.outputs = null
			view.selectedTaskId = null
			view.taskStatus = null
			lastTask = null
		})
		modalMountPoint.addEventListener('background-notify', (data) => {
			setNotifyReady(lastTask.id, data.detail).then(res => {
				view.isNotifyEnabled = data.detail
			})
		})
		modalMountPoint.addEventListener('cancel-task', () => {
			cancelScheduling()
			cancelTaskPolling()
			cancelTaskPositionPolling()
			setNotifyReady(lastTask.id, false)
			cancelTask(lastTask.id).then(res => {
				view.loading = false
				view.showSyncTaskRunning = false
				view.taskPosition = null
				view.selectedTaskId = null
				view.outputs = null
				view.taskStatus = null
				lastTask = null
			})
		})
		modalMountPoint.addEventListener('action-button-clicked', (data) => {
			if (data.detail.button?.onClick) {
				lastTask.output = data.detail.output
				data.detail.button.onClick(lastTask)
			}
			app.unmount()
			OCA.Assistant.isAssistantDialogOpen = false
		})
	})
}

function updateTask(task, object, updateOutput = true) {
	if (task?.status === TASK_STATUS_STRING.running) {
		object.progress = task?.progress * 100
	}
	object.taskStatus = task?.status
	object.scheduledAt = task?.scheduledAt
	if (updateOutput) {
		console.debug('[assistant] polling update output')
		object.outputs = task?.output
	}
	object.startedAt = task?.startedAt
	object.completionExpectedAt = task?.completionExpectedAt
}

function updateTaskPosition(position, object) {
	const n = Number(position)
	object.taskPosition = Number.isFinite(n) ? n : null
}

/**
 * Poll the task position
 *
 * @param {number} taskId the task ID
 * @param {object} obj the object to update
 * @param {(position: number, obj: object) => void} callback the function to call to update the object
 * @return {Promise<void>}
 */
export async function pollTaskPosition(taskId, obj, callback = updateTaskPosition) {
	const { isCancel } = await import('@nextcloud/axios')
	return new Promise((resolve, reject) => {
		cancelTaskPositionPolling()
		window.assistantPollPositionTaskId = taskId
		const abortController = new AbortController()
		window.assistantPollPositionAbortController = abortController
		let retryDelay = 5000
		let settled = false

		const cleanup = () => {
			clearTimeout(window.assistantPollPositionTimerId)
			clearTimeout(window.assistantPollPositionRetryTimerId)
			window.assistantPollPositionTimerId = null
			window.assistantPollPositionRetryTimerId = null
			if (window.assistantPollPositionTaskId === taskId) {
				window.assistantPollPositionTaskId = null
				window.assistantPollPositionAbortController = null
			}
		}

		const safeReject = (err) => {
			if (!settled) {
				settled = true
				cleanup()
				reject(err)
			}
		}

		const safeResolve = (val) => {
			if (!settled) {
				settled = true
				cleanup()
				resolve(val)
			}
		}

		abortController.signal.addEventListener('abort', () => {
			safeReject(new TaskPollCancelledError('pollTaskPosition aborted'))
		})

		const pollPositionOnce = () => {
			if (window.assistantPollPositionTaskId !== taskId) {
				safeReject(new TaskPollCancelledError('pollTaskPosition cancelled'))
				return
			}

			getTaskPosition(taskId, abortController.signal).then(response => {
				if (window.assistantPollPositionTaskId !== taskId) {
					safeReject(new TaskPollCancelledError('pollTaskPosition cancelled'))
					return
				}
				const taskPosition = response.data?.ocs?.data
				if (obj) {
					callback(taskPosition, obj)
				}
				if (window.assistantPollPositionTaskId === taskId) {
					window.assistantPollPositionRetryTimerId = null
					window.assistantPollPositionTimerId = setTimeout(pollPositionOnce, 5000)
				}
			}).catch(error => {
				if (isCancel(error)) {
					console.debug('[assistant] pollPosition request cancelled', error)
					safeReject(new TaskPollCancelledError('pollTaskPosition request cancelled'))
					return
				}

				const status = error?.response?.status ?? error?.status
				console.debug('[assistant] pollPosition request failed', error)

				if (status === 404) {
					safeReject(new Error('task-not-found'))
					return
				}
				if (status === 412) {
					safeResolve()
					return
				}
				if (status >= 400 && status < 500) {
					safeReject(new Error('pollTaskPosition non-retryable error: ' + status))
					return
				}

				console.warn('[assistant] pollPosition temporary failure, will retry in ' + retryDelay + 'ms', error)
				if (window.assistantPollPositionTaskId === taskId) {
					window.assistantPollPositionRetryTimerId = setTimeout(() => {
						retryDelay = Math.min(retryDelay * 2, 60000)
						pollPositionOnce()
					}, retryDelay)
				} else {
					safeReject(new TaskPollCancelledError('pollTaskPosition cancelled during retry backoff'))
				}
			})
		}

		pollPositionOnce()
	})
}

/**
 * Poll the task to update its status
 *
 * @param {number} taskId the task ID
 * @param {object} obj the object to update
 * @param {boolean} updateOutput whether to update the task output from the polling data or not
 * @param {(task: object, object: object, updateOutput: boolean) => void} callback the function to call to update the object
 * @return {Promise<object>}
 */
export async function pollTask(taskId, obj, updateOutput = true, callback = updateTask) {
	const { isCancel } = await import('@nextcloud/axios')
	return new Promise((resolve, reject) => {
		cancelTaskPolling()
		window.assistantPollTaskId = taskId
		const abortController = new AbortController()
		window.assistantPollAbortController = abortController
		let retryDelay = 5000
		let settled = false

		const cleanup = () => {
			clearTimeout(window.assistantPollTimerId)
			clearTimeout(window.assistantPollRetryTimerId)
			window.assistantPollTimerId = null
			window.assistantPollRetryTimerId = null
			if (window.assistantPollTaskId === taskId) {
				window.assistantPollTaskId = null
				window.assistantPollAbortController = null
			}
		}

		const safeReject = (err) => {
			if (!settled) {
				settled = true
				cleanup()
				reject(err)
			}
		}

		const safeResolve = (val) => {
			if (!settled) {
				settled = true
				cleanup()
				resolve(val)
			}
		}

		abortController.signal.addEventListener('abort', () => {
			safeReject(new TaskPollCancelledError('pollTask aborted'))
		})

		const pollOnce = () => {
			if (window.assistantPollTaskId !== taskId) {
				safeReject(new TaskPollCancelledError())
				return
			}

			getTask(taskId, abortController.signal).then(response => {
				if (window.assistantPollTaskId !== taskId) {
					safeReject(new TaskPollCancelledError())
					return
				}
				const task = response.data?.ocs?.data?.task
				if (obj) {
					callback(task, obj, updateOutput)
				}
				if (![TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(task?.status)) {
					safeResolve(task)
				} else if (window.assistantPollTaskId === taskId) {
					window.assistantPollTimerId = setTimeout(pollOnce, 2000)
				}
			}).catch(error => {
				if (isCancel(error)) {
					console.debug('[assistant] poll request cancelled', error)
					safeReject(new TaskPollCancelledError())
					return
				}

				const status = error?.response?.status ?? error?.status
				console.debug('[assistant] poll request failed', error)

				if (status === 404) {
					safeReject(new Error('task-not-found'))
					return
				}
				if (status >= 400 && status < 500) {
					safeReject(new Error('pollTask non-retryable error: ' + status))
					return
				}

				console.warn('[assistant] poll temporary failure, will retry in ' + retryDelay + 'ms', error)
				if (window.assistantPollTaskId === taskId) {
					window.assistantPollRetryTimerId = setTimeout(() => {
						retryDelay = Math.min(retryDelay * 2, 60000)
						pollOnce()
					}, retryDelay)
				} else {
					safeReject(new TaskPollCancelledError('pollTask cancelled during retry backoff'))
				}
			})
		}

		pollOnce()
	})
}

export async function cancelTaskPolling() {
	window.assistantPollAbortController?.abort()
	clearTimeout(window.assistantPollTimerId)
	clearTimeout(window.assistantPollRetryTimerId)
	window.assistantPollTimerId = null
	window.assistantPollRetryTimerId = null
	window.assistantPollTaskId = null
	window.assistantPollAbortController = null
}

export async function cancelTaskPositionPolling() {
	window.assistantPollPositionAbortController?.abort()
	clearTimeout(window.assistantPollPositionTimerId)
	clearTimeout(window.assistantPollPositionRetryTimerId)
	window.assistantPollPositionTimerId = null
	window.assistantPollPositionRetryTimerId = null
	window.assistantPollPositionTaskId = null
	window.assistantPollPositionAbortController = null
}

export async function cancelScheduling() {
	window.assistantSchedulingAbortController?.abort()
	window.assistantSchedulingAbortController = null
}

export async function getTask(taskId, signal = null) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	const url = generateOcsUrl('taskprocessing/task/{taskId}', { taskId })
	const config = signal ? { signal } : {}
	return axios.get(url, config)
}

export async function getTaskPosition(taskId, signal = null) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	const url = generateOcsUrl('taskprocessing/tasks/{taskId}/queue_position', { taskId })
	const config = signal ? { signal } : {}
	return axios.get(url, config)
}

export async function getNotifyReady(taskId) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/notify', { taskId })
	return axios.get(url, {})
}

export async function setNotifyReady(taskId, enable) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	return axios({
		method: enable ? 'post' : 'delete',
		url: generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/notify', { taskId }),
	})
}

export async function cancelTask(taskId) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	const url = generateOcsUrl('taskprocessing/tasks/{taskId}/cancel', { taskId })
	return axios.post(url, {})
}

/**
 * Send a request to schedule a task
 *
 * @param {string} appId the scheduling app id
 * @param {string} customId the task custom ID
 * @param {string} taskType the task type class
 * @param {Array} inputs the task input texts as an array
 * @param {AbortSignal} signal optional abort signal for cancellation
 * @return {Promise<object>}
 */
export async function scheduleTask(appId, customId, taskType, inputs, signal = null) {
	const { default: axios } = await import('@nextcloud/axios')
	const { generateOcsUrl } = await import('@nextcloud/router')
	if (taskType === 'core:text2text:translate') {
		saveLastTargetLanguage(inputs.target_language)
	}
	const url = generateOcsUrl('taskprocessing/schedule')
	const params = {
		input: inputs,
		type: taskType,
		appId,
		customId,
		preferStreaming: true,
	}
	const config = signal ? { signal } : {}
	return axios.post(url, params, config)
}

export async function saveLastSelectedTaskType(taskType) {
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
	return axios.get(url, req).catch(error => {
		if (error.response?.status === 404) {
			console.debug(t('assistant', 'No last task type available, falling back to default'))
			return { data: 'chatty-llm' }
		}

		console.error(error)
	})
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
		openAssistantTask(response.data?.ocs?.data?.task, {}).catch(error => {
			console.error(error.message)
		})
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
	if (OCA.Assistant.isAssistantDialogOpen) {
		throw new Error('Assistant dialog is already open')
	}
	OCA.Assistant.isAssistantDialogOpen = true

	const { createApp } = await import('vue')
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
			modalMountPoint.classList.add('insideViewer')
		} else {
			// so the viewer can be later opened on top of the assistant
			document.querySelector('body').insertBefore(modalMountPoint, content.nextSibling)
		}
	}

	const app = createApp(
		AssistantTextProcessingModal,
		{
			isInsideViewer,
			initSelectedTaskId: task.id,
			initInputs: task.input,
			initOutputs: task.output ?? {},
			initSelectedTaskTypeId: task.type,
			actionButtons,
		},
	)
	app.mixin({ methods: { t, n } })
	app.use(PrimeVue, {
		theme: {
			preset: Aura,
			options: {
				prefix: 'p',
				darkModeSelector: 'system',
				cssLayer: false,
			},
		},
	})
	const view = app.mount(modalMountPoint)
	let lastTask = task

	// notify push stuff
	const isListeningTo = {}
	// listen only if needed
	// return true if notify_push is available
	// we can't cleanup isListeningTo because there is no way to remove a handler with @nextcloud/notify_push
	const listenToTaskNotifications = (pushTaskId) => {
		if (isListeningTo[pushTaskId]) {
			return true
		}
		// attempt to listen to push notifications to get the intermediate output
		const pushChannel = 'taskprocessing:task_id_' + pushTaskId
		const hasPush = listen(pushChannel, (type, body) => {
			console.debug('[assistant] received push notification', type, body)
			if (pushTaskId === view.selectedTaskId) {
				view.outputs = body ?? null
			} else {
				console.debug('[assistant] ignoring push notification for task', pushTaskId, 'the selected one is', view.selectedTaskId)
			}
		})
		if (hasPush) {
			isListeningTo[pushTaskId] = true
		}
		return hasPush
	}

	modalMountPoint.addEventListener('cancel', () => {
		cancelScheduling()
		cancelTaskPolling()
		cancelTaskPositionPolling()
		app.unmount()
		OCA.Assistant.isAssistantDialogOpen = false
	})
	modalMountPoint.addEventListener('submit', (data) => {
		const controller = new AbortController()
		window.assistantSchedulingAbortController = controller
		scheduleTask(task.appId, task.identifier ?? '', data.detail.selectedTaskTypeId, data.detail.inputs, controller.signal)
			.then((response) => {
				if (window.assistantSchedulingAbortController !== controller) {
					return
				}
				cancelScheduling()
				console.debug('scheduled task', response.data?.ocs?.data?.task)
			})
			.catch(error => {
				if (controller.signal.aborted) {
					return
				}
				if (window.assistantSchedulingAbortController === controller) {
					cancelScheduling()
				}
				app.unmount()
				OCA.Assistant.isAssistantDialogOpen = false
				console.error('Assistant scheduling error', error)
				showError(
					t('assistant', 'Assistant failed to schedule your task')
					+ '. ' + t('assistant', 'Please try again and inform the server administrators if this issue persists.'),
				)
			})
	})
	const syncSubmit = (inputs, taskTypeId, newTaskCustomId = '') => {
		cancelScheduling()
		view.loading = true
		view.showSyncTaskRunning = true
		view.taskPosition = null
		view.isNotifyEnabled = false
		view.expectedRuntime = null
		view.startedAt = null
		view.completionExpectedAt = null
		view.inputs = inputs
		view.outputs = null
		view.selectedTaskTypeId = taskTypeId

		const controller = new AbortController()
		window.assistantSchedulingAbortController = controller
		scheduleTask('assistant', newTaskCustomId, taskTypeId, inputs, controller.signal)
			.then((response) => {
				if (window.assistantSchedulingAbortController !== controller) {
					return
				}
				cancelScheduling()
				const task = response.data?.ocs?.data?.task
				lastTask = task
				view.selectedTaskId = lastTask?.id
				view.expectedRuntime = (lastTask?.completionExpectedAt - lastTask?.scheduledAt) || null
				view.startedAt = lastTask?.startedAt || null
				view.completionExpectedAt = lastTask?.completionExpectedAt || null
				const hasPush = listenToTaskNotifications(task.id)
				console.debug('[assistant] HAS PUSH', hasPush)

				pollTaskPosition(task.id, view).then(() => {
					console.debug('[assistant] pollTaskPosition: the task is not scheduled anymore', task.id)
				}).catch(error => {
					if (error instanceof TaskPollCancelledError) {
						return
					}
					console.debug('[assistant] pollPosition error', task.id, error.message)
				})
				pollTask(task.id, view, !hasPush).then(finishedTask => {
					if (finishedTask.status === TASK_STATUS_STRING.successful) {
						view.outputs = finishedTask?.output
					} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
						if (finishedTask.userFacingErrorMessage) {
							showError(
								t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
								+ ': ' + finishedTask.userFacingErrorMessage,
							)
						} else {
							showError(
								t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
								+ '. ' + t('assistant', 'Please inform the server administrators of this issue.'),
							)
						}
						console.error('[assistant] Task failed', finishedTask)
						view.outputs = null
					}
					// resolve(finishedTask)
					view.loading = false
					view.showSyncTaskRunning = false
					view.taskPosition = null
					cancelTaskPositionPolling()
					emit('assistant:task:updated', finishedTask)
				}).catch(error => {
					if (error instanceof TaskPollCancelledError) {
						return
					}
					console.debug('[assistant] poll error', error)
					view.outputs = null
					view.taskPosition = null
					cancelTaskPositionPolling()
					if (error.message === 'task-not-found') {
						view.loading = false
						view.showSyncTaskRunning = false
						view.isNotifyEnabled = false
						view.selectedTaskId = null
						lastTask = null
						showError(t('assistant', 'The current Assistant task could not be found'))
					}
				})
			})
			.catch(error => {
				if (controller.signal.aborted) {
					return
				}
				if (window.assistantSchedulingAbortController === controller) {
					cancelScheduling()
				}
				view.loading = false
				view.showSyncTaskRunning = false
				view.taskPosition = null
				console.error('Assistant scheduling error', error?.response?.data?.ocs?.data?.message)
				showError(t('assistant', 'Assistant error') + ': ' + t('assistant', 'Something went wrong when scheduling the task'))
			})
	}
	modalMountPoint.addEventListener('sync-submit', (data) => {
		syncSubmit(data.detail.inputs, data.detail.selectedTaskTypeId, task.identifier ?? '')
	})
	modalMountPoint.addEventListener('try-again', (data) => {
		cancelScheduling()
		const task = data.detail
		syncSubmit(task.input, task.type)
	})
	modalMountPoint.addEventListener('load-task', (data) => {
		const task = data.detail
		cancelScheduling()
		cancelTaskPolling()
		cancelTaskPositionPolling()
		view.showSyncTaskRunning = false
		view.taskPosition = null
		view.isNotifyEnabled = false
		view.loading = false
		view.taskStatus = task.status

		view.selectedTaskTypeId = task.type
		view.inputs = task.input
		view.outputs = task.status === TASK_STATUS_STRING.successful ? task.output : null
		view.selectedTaskId = task.id
		lastTask = task

		if ([TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(task?.status)) {
			getTask(task.id).then(response => {
				if (task.id !== view.selectedTaskId) {
					console.debug('[assistant] ignoring stale getTask response for task', task.id, 'selected is', view.selectedTaskId)
					return
				}
				const updatedTask = response.data?.ocs?.data?.task

				if (![TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(updatedTask?.status)) {
					view.selectedTaskTypeId = updatedTask.type
					view.inputs = updatedTask.input
					view.outputs = updatedTask.status === TASK_STATUS_STRING.successful ? updatedTask.output : null
					view.selectedTaskId = updatedTask.id
					view.taskStatus = updatedTask.status
					lastTask = updatedTask
					return
				}

				getNotifyReady(task.id).then(response => {
					if (task.id !== view.selectedTaskId) {
						return
					}
					view.isNotifyEnabled = !!response.data?.ocs?.data?.id
				}).catch(error => {
					console.error('[assistant] get task notification status error', error)
				})

				view.loading = true
				view.showSyncTaskRunning = true
				view.taskPosition = null
				view.progress = null
				view.expectedRuntime = (updatedTask?.completionExpectedAt - updatedTask?.scheduledAt) || null
				view.startedAt = lastTask?.startedAt || null
				view.completionExpectedAt = lastTask?.completionExpectedAt || null

				const hasPush = listenToTaskNotifications(task.id)

				pollTaskPosition(updatedTask.id, view).then(() => {
					console.debug('[assistant] pollTaskPosition: the task is not scheduled anymore', updatedTask.id)
				}).catch(error => {
					if (error instanceof TaskPollCancelledError) {
						return
					}
					console.debug('[assistant] pollPosition error', updatedTask.id, error.message)
				})
				pollTask(updatedTask.id, view, !hasPush).then(finishedTask => {
					console.debug('pollTask.then', finishedTask)
					if (finishedTask.status === TASK_STATUS_STRING.successful) {
						view.outputs = finishedTask?.output
						view.selectedTaskId = finishedTask?.id
					} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
						if (finishedTask.userFacingErrorMessage) {
							showError(
								t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
								+ ': ' + finishedTask.userFacingErrorMessage,
							)
						} else {
							showError(
								t('assistant', 'The server failed to process your task with ID {id}', { id: finishedTask.id })
								+ '. ' + t('assistant', 'Please inform the server administrators of this issue.'),
							)
						}
						console.error('[assistant] Task failed', finishedTask)
						view.outputs = null
					}
					// resolve(finishedTask)
					view.loading = false
					view.showSyncTaskRunning = false
					view.taskPosition = null
					cancelTaskPositionPolling()
					emit('assistant:task:updated', finishedTask)
				}).catch(error => {
					if (error instanceof TaskPollCancelledError) {
						return
					}
					console.debug('[assistant] poll error', error)
					view.taskPosition = null
					cancelTaskPositionPolling()
					if (error.message === 'task-not-found') {
						view.loading = false
						view.showSyncTaskRunning = false
						view.isNotifyEnabled = false
						view.outputs = null
						view.selectedTaskId = null
						lastTask = null
						showError(t('assistant', 'The current Assistant task could not be found'))
					}
				})
			}).catch(error => {
				console.error(error)
			})
		}
	})
	modalMountPoint.addEventListener('new-task', () => {
		console.debug('[assistant] new task')
		cancelScheduling()
		cancelTaskPolling()
		cancelTaskPositionPolling()
		view.loading = false
		view.showSyncTaskRunning = false
		view.taskPosition = null
		view.isNotifyEnabled = false
		view.outputs = null
		view.selectedTaskId = null
		view.taskStatus = null
		lastTask = null
	})
	modalMountPoint.addEventListener('background-notify', (data) => {
		setNotifyReady(lastTask.id, data.detail).then(res => {
			view.isNotifyEnabled = data.detail
		})
	})
	modalMountPoint.addEventListener('cancel-task', () => {
		cancelScheduling()
		cancelTaskPolling()
		cancelTaskPositionPolling()
		setNotifyReady(lastTask.id, false)
		cancelTask(lastTask.id).then(res => {
			view.loading = false
			view.showSyncTaskRunning = false
			view.taskPosition = null
			view.selectedTaskId = null
			view.outputs = null
			view.taskStatus = null
			lastTask = null
		})
	})
	modalMountPoint.addEventListener('action-button-clicked', (data) => {
		if (data.detail.button?.onClick) {
			lastTask.output = data.detail.output
			data.detail.button.onClick(lastTask)
		}
		app.unmount()
		OCA.Assistant.isAssistantDialogOpen = false
	})
}

export async function addAssistantMenuEntry() {
	// changed in NC 31 header-right -> header-end
	const headerRight = document.querySelector('#header .header-right') ?? document.querySelector('#header .header-end')
	const menuEntry = document.createElement('div')
	menuEntry.id = 'assistant'
	headerRight.prepend(menuEntry)

	const { createApp } = await import('vue')
	const { default: AssistantHeaderMenuEntry } = await import('./components/AssistantHeaderMenuEntry.vue')

	const view = createApp(AssistantHeaderMenuEntry, {})
	view.mixin({ methods: { t, n } })
	view.mount(menuEntry)

	menuEntry.addEventListener('click', () => {
		if (OCA.Assistant.openingAssistant) {
			return
		}
		OCA.Assistant.openingAssistant = true
		setTimeout(() => {
			OCA.Assistant.openingAssistant = false
		}, 1000)
		openAssistantForm({ appId: 'assistant' })
			.then(r => {
				console.debug('[Assistant header menu entry] scheduled task', r)
			})
			.catch(error => {
				console.error('[Assistant header menu entry] Assistant openAssistantForm promise rejected:', error.message)
			})
	})
}
