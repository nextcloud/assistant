import Vue from 'vue'

import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

import PlainTextResultPage from './views/PlainTextResultPage.vue'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('stt_helper', 'js/') // eslint-disable-line

Vue.mixin({ methods: { t, n } })

const View = Vue.extend(PlainTextResultPage)
new View().$mount('#assistant-stt-content')
