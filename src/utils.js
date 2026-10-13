/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

const uploadEndpointUrl = generateOcsUrl('/apps/assistant/api/v1/input-file')

let mytimer = 0
export function delay(callback, ms = 0) {
	clearTimeout(mytimer)
	mytimer = setTimeout(callback, ms)
}

/**
 * Parse special symbols in text like &amp; &lt; &gt; &sect;
 * FIXME upstream: https://github.com/nextcloud-libraries/nextcloud-vue/issues/4492
 *
 * @param {string} text The text to parse
 */
export function parseSpecialSymbols(text) {
	const temp = document.createElement('textarea')
	temp.innerHTML = text.replace(/&/gmi, '&amp;')
	text = temp.value.replace(/&amp;/gmi, '&').replace(/&lt;/gmi, '<')
		.replace(/&gt;/gmi, '>').replace(/&sect;/gmi, '§')
		.replace(/^\s+|\s+$/g, '') // remove trailing and leading whitespaces
		.replace(/\r\n|\n|\r/gm, '\n') // remove line breaks
	return text
}

export function uploadInputFile(file) {
	const formData = new FormData()
	formData.append('data', file)
	formData.append('filename', file.name)
	return axios.post(uploadEndpointUrl, formData)
}

/**
 * Ask for browser notification permission, meant to be called from a user
 * gesture handler. Denial is not surfaced: the in-app notification still
 * works without browser notifications.
 *
 * @return {Promise<boolean>} true if the permission was freshly granted
 * (the notifications app only registers its web push subscription on page
 * load, so the caller should reload once to activate browser delivery)
 */
export async function requestBrowserNotificationPermission() {
	if (!('Notification' in window) || Notification.permission !== 'default') {
		return false
	}
	let permission
	try {
		permission = await Notification.requestPermission()
	} catch (error) {
		console.debug('Browser notification permission request failed', error)
		return false
	}
	return permission === 'granted'
}
