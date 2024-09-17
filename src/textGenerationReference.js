// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.js'

registerCustomPickerElement('assistant_text', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { default: TextResultCustomPickerElement } = await import('./views/TextResultCustomPickerElement.vue')
	const Element = Vue.extend(TextResultCustomPickerElement)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
			taskType: 'core:text2text',
			outputKey: 'output',
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
