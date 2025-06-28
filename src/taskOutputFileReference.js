/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerWidget } from '@nextcloud/vue/components/NcRichText'

registerWidget('assistant_task-output-file', async (el, { richObjectType, richObject, accessible }) => {
	const { createApp } = await import('vue')
	const { default: TaskOutputFileReferenceWidget } = await import(/* webpackChunkName: "reference-maplibre-lazy" */'./views/TaskOutputFileReferenceWidget.vue')

	const app = createApp(
		TaskOutputFileReferenceWidget,
		{
			richObjectType,
			richObject,
			accessible,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)
}, () => {}, { hasInteractiveView: false })
