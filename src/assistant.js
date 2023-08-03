import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('textprocessing_assistant', 'js/') // eslint-disable-line

/**
 * Creates an assistant modal and return a promise which provides the result
 *
 * @param {boolean} isInsideViewer Should be true if this function is called while the Viewer is displayed
 * @return {Promise<unknown>}
 */
export async function openAssistant(appId, identifier = '', taskType = null, inputText = '', isInsideViewer = undefined) {
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

function init() {
	if (!OCA.TPAssistant) {
		/**
		 * @namespace
		 */
		OCA.TPAssistant = {
			openAssistant,
		}
	}
}

init()
