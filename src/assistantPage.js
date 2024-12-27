/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import AssistantPage from './views/AssistantPage.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(AssistantPage)
new View().$mount('#content')
