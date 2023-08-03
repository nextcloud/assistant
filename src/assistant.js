import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('textprocessing_assistant', 'js/') // eslint-disable-line

// Creates an assistant modal and return a promise which provides the result
// TODO jsdoc
// OCA.TPAssistant.openTextProcessingModal('app1', 'IDID', 'OCP\\TextProcessing\\SummaryTaskType', 'megainput').then(r => {console.debug('yeyeyeyeyeye', r)})
export async function openTextProcessingModal(appId, identifier = '', taskType = null, inputText = '', isInsideViewer = undefined) {
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
			scheduleTask({ appId, identifier, taskType: data.taskTypeId, input: data.input })
				.then((response) => {
					console.debug('aaaa ASSISTANT schedule success', response)
					resolve(response.data?.ocs?.data?.task)
				})
				.catch(error => {
					console.error('aaaaa ASSISTANT schedule error', error)
					reject(new Error('Assistant scheduling error'))
				})
		})
	})
}

async function scheduleTask({ appId, identifier, taskType, input }) {
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

function handleNotification(event) {
	console.debug('aaaaaa -------- handle notification', event)
	if (event.notification.app !== 'textprocessing_assistant' || event.action.type !== 'WEB') {
		return
	}
	event.cancelAction = true
	showResults(event.notification.objectId)
}

async function subscribeToNotifications() {
	const { subscribe } = await import(/* webpackChunkName: "router-lazy" */'@nextcloud/event-bus')
	subscribe('notifications:action:execute', handleNotification)
	console.debug('aaaaa i subscribed', subscribe)
}

async function showResults(taskId) {
	const { default: axios } = await import(/* webpackChunkName: "axios-lazy" */'@nextcloud/axios')
	const { generateOcsUrl } = await import(/* webpackChunkName: "router-lazy" */'@nextcloud/router')
	const url = generateOcsUrl('textprocessing/task/{taskId}', { taskId })
	axios.get(url).then(response => {
		console.debug('aaaaaa result of task', response.data)
		openResultModal(response.data.ocs.data.task)
	}).catch(error => {
		console.error(error)
	})
}

async function openResultModal(task) {
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

function init() {
	if (!OCA.TPAssistant) {
		/**
		 * @namespace
		 */
		OCA.TPAssistant = {
			openTextProcessingModal,
		}
	}
}

init()
subscribeToNotifications()
