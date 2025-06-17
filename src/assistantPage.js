/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AssistantPage from './views/AssistantPage.vue'

const app = createApp(AssistantPage)
app.mixin({ methods: { t, n } })
app.mount('#content')
