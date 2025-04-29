/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerWidget } from '@nextcloud/vue/dist/Components/NcRichText.js'

registerWidget('assistant_task-output-file', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { default: TaskOutputFileReferenceWidget } = await import('./views/TaskOutputFileReferenceWidget.vue')
	const Widget = Vue.extend(TaskOutputFileReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
}, () => {}, { hasInteractiveView: false })
