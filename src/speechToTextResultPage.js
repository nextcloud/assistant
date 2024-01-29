import Vue from 'vue'

import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'

import PlainTextResultPage from './views/PlainTextResultPage.vue'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('stt_helper', 'js/') // eslint-disable-line

Vue.mixin({ methods: { t, n } })

const initialState = loadState('assistant', 'plain-text-result')

const View = Vue.extend(PlainTextResultPage)
new View({
	propsData: {
		task: initialState.task,
	},
}).$mount('#assistant-stt-content')
