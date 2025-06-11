/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/components/NcRichText'

registerCustomPickerElement('assistant_speech_to_text', async (el, { providerId, accessible }) => {
	const { createApp } = await import('vue')
	const { default: TextResultCustomPickerElement } = await import('./views/TextResultCustomPickerElement.vue')

	const app = createApp(
		TextResultCustomPickerElement,
		{
			providerId,
			accessible,
			taskType: 'core:audio2text',
			outputKey: 'output',
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)

	return new NcCustomPickerRenderResult(el, app)
}, (el, renderResult) => {
	console.debug('Stt custom destroy callback. el:', el, 'renderResult:', renderResult)
	renderResult.object.unmount()
}, 'normal')
