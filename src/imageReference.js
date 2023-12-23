// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { registerWidget, registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.js'

import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('assistant', 'js/') // eslint-disable-line

registerCustomPickerElement('assistant_image', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	Vue.mixin({ methods: { t, n } })
	const { default: Text2ImageCustomPickerElement } = await import(/* webpackChunkName: "reference-picker-lazy" */'./views/Text2Image/Text2ImageCustomPickerElement.vue')
	const Element = Vue.extend(Text2ImageCustomPickerElement)

	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})

registerWidget('assistant_image', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "vue-lazy" */'vue')
	Vue.mixin({ methods: { t, n } })
	const { default: Text2ImageReferenceWidget } = await import(/* webpackChunkName: "reference-lazy" */'./views/Text2Image/Text2ImageReferenceWidget.vue')
	const Widget = Vue.extend(Text2ImageReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})
