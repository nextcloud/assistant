/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/components/NcRichText'

registerCustomPickerElement('assistant_sticker_generation', async (el, { providerId, accessible }) => {
	const { createApp } = await import('vue')
	const { default: ImageResultCustomPickerElement } = await import('./views/ImageResultCustomPickerElement.vue')
	const app = createApp(
		ImageResultCustomPickerElement,
		{
			providerId,
			accessible,
			taskType: 'assistant:text2sticker',
			outputKey: 'image',
			multipleImages: false,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)

	return new NcCustomPickerRenderResult(el, app)
}, (el, renderResult) => {
	renderResult.object.unmount()
})
