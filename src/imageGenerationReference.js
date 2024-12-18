/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.js'

registerCustomPickerElement('assistant_image_generation', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { default: ImageResultCustomPickerElement } = await import('./views/ImageResultCustomPickerElement.vue')
	const Element = Vue.extend(ImageResultCustomPickerElement)

	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
			taskType: 'core:text2image',
			outputKey: 'images',
			multipleImages: true,
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
