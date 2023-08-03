import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('textprocessing_assistant', 'js/') // eslint-disable-line

/**
 * Creates an assistant modal and return a promise which provides the result
 *
 * OCA.TPAssistant.openAssistantForm('my_app_id', 'my task identifier', 'OCP\\TextProcessing\\FreePromptTaskType', 'count to 3').then(r => {console.debug('scheduled task', r.data.ocs.data.task)})
 *
 * @param {string} appId the scheduling app id
 * @param {string} identifier the task identifier
 * @param {string} taskType the task type class
 * @param {string} inputText optional initial input text
 * @param {boolean} isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @return {Promise<unknown>}
 */
export async function openAssistantForm(appId, identifier = '', taskType = null, inputText = '', isInsideViewer = undefined) {
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	const { default: AssistantModal } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantModal.vue')
	Vue.mixin({ methods: { t, n } })

	return new Promise((resolve, reject) => {
		const modalId = 'assistantModal'
		const modalElement = document.createElement('div')
		modalElement.id = modalId
		document.body.append(modalElement)

		const View = Vue.extend(AssistantModal)
		const view = new View({
			propsData: {
				isInsideViewer,
				input: inputText,
				selectedTaskTypeId: taskType,
			},
		}).$mount(modalElement)

		view.$on('cancel', () => {
			view.$destroy()
			reject(new Error('User cancellation'))
		})
		view.$on('submit', (data) => {
			view.$destroy()
			scheduleTask(appId, identifier, data.taskTypeId, data.input)
				.then((response) => {
					resolve(response.data?.ocs?.data?.task)
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
					reject(new Error('Assistant scheduling error'))
				})
		})
	})
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
async function scheduleTask(appId, identifier, taskType, input) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-lazy" */'@nextcloud/router')
	const url = generateOcsUrl('textprocessing/schedule', 2)
	const params = {
		input,
		type: taskType,
		appId,
		identifier,
	}
	return axios.post(url, params)
}

/**
 * Check if we want to cancel a notification action click and handle it ourselves
 *
 * @param {event} event the notification event
 */
function handleNotification(event) {
	if (event.notification.app !== 'textprocessing_assistant' || event.action.type !== 'WEB') {
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
 * Listen to an event emitted on the event-bus when a notification action or a browser notification is clicked
 *
 * @return {Promise<void>}
 */
async function subscribeToNotifications() {
	const { subscribe } = await import(/* webpackChunkName: "router-lazy" */'@nextcloud/event-bus')
	subscribe('notifications:action:execute', handleNotification)
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
	const url = generateOcsUrl('textprocessing/task/{taskId}', { taskId })
	axios.get(url).then(response => {
		openAssistantResult(response.data.ocs.data.task)
	}).catch(error => {
		console.error(error)
	})
}

/**
 * Open an assistant modal to show  the result of a task
 *
 * @param {object} task the task we want to see the result of
 * @return {Promise<void>}
 */
async function openAssistantResult(task) {
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
			output: task.output,
			selectedTaskTypeId: task.type,
			readonly: true,
		},
	}).$mount(modalElement)

	view.$on('cancel', () => {
		view.$destroy()
	})
}

async function addAssistantMenuEntry() {
	const headerRight = document.querySelector('#header .header-right')
	const menuEntry = document.createElement('div')
	menuEntry.id = 'assistant'
	headerRight.prepend(menuEntry)

	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	const { default: AssistantHeaderMenuEntry } = await import(/* webpackChunkName: "assistant-modal-lazy" */'./components/AssistantHeaderMenuEntry.vue')
	Vue.mixin({ methods: { t, n } })

	const View = Vue.extend(AssistantHeaderMenuEntry)
	const view = new View({
		propsData: {},
	}).$mount(menuEntry)

	view.$on('click', () => {
		openAssistantForm('textprocessing_assistant')
			.then(r => {
				console.debug('scheduled task', r.data.ocs.data.task)
			})
	})
}

/**
 * Expose OCA.TPAssistant.openTextProcessingModal to let apps use the assistant
 */
function init() {
	if (!OCA.TPAssistant) {
		/**
		 * @namespace
		 */
		OCA.TPAssistant = {
			openAssistantForm,
		}
	}
	addAssistantMenuEntry()
}

init()
subscribeToNotifications()
