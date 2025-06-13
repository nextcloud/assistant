/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/components/NcRichText'

registerCustomPickerElement('assistant_image_generation', async (el, { providerId, accessible }) => {
	const { createApp } = await import('vue')
	const { default: ImageResultCustomPickerElement } = await import('./views/ImageResultCustomPickerElement.vue')

	const app = createApp(
		ImageResultCustomPickerElement,
		{
			providerId,
			accessible,
			taskType: 'core:text2image',
			outputKey: 'images',
			multipleImages: true,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)

	return new NcCustomPickerRenderResult(el, app)
}, (el, renderResult) => {
	renderResult.object.unmount()
})
