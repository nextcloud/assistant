/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.js'

registerCustomPickerElement('assistant_speech_to_text', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { default: TextResultCustomPickerElement } = await import('./views/TextResultCustomPickerElement.vue')
	const Element = Vue.extend(TextResultCustomPickerElement)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
			taskType: 'core:audio2text',
			outputKey: 'output',
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	console.debug('Stt custom destroy callback. el:', el, 'renderResult:', renderResult)
	renderResult.object.$destroy()
}, 'normal')
