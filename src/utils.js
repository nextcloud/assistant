/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

let mytimer = 0
export function delay(callback, ms = 0) {
	clearTimeout(mytimer)
	mytimer = setTimeout(callback, ms)
}

/**
 * Safely extract a displayable error message string.
 * Prevents [object Object] from being shown to the user when
 * the API returns a non-string error value.
 *
 * @param {*} value The error value to extract a message from
 * @return {string|undefined} The error message string or undefined
 */
export function getErrorMessage(value) {
	if (typeof value === 'string') {
		return value
	}
	if (value?.message && typeof value.message === 'string') {
		return value.message
	}
	return undefined
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
