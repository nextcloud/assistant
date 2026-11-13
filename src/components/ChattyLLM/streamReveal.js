/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Maximum time it takes to display newly received streamed text.
 *
 * It is shorter than the 250 ms interval at which integration_openai reports
 * intermediate output, so the displayed text has caught up with the received
 * text before the next chunk arrives.
 */
export const STREAM_REVEAL_DURATION_MS = 200

/**
 * Delay between two reveal steps (about one frame).
 */
export const STREAM_REVEAL_STEP_MS = 16

/**
 * Get the length of streamed text to display `elapsedMs` after it was received.
 *
 * The part of `text` after `fromLength` is revealed linearly over `durationMs`,
 * so the speed follows how fast the text arrives instead of being a fixed
 * number of characters per second. After `durationMs`, all of `text` is displayed.
 *
 * @param {string} text The whole text received so far
 * @param {number} fromLength Length that was already displayed when the text was received
 * @param {number} elapsedMs Time since the text was received
 * @param {number} durationMs Time after which all of the text is displayed
 * @return {number} The length to display. It never splits a surrogate pair.
 */
export function getRevealLength(text, fromLength, elapsedMs, durationMs = STREAM_REVEAL_DURATION_MS) {
	const toLength = text.length
	const start = Math.min(Math.max(fromLength, 0), toLength)
	if (start === toLength || durationMs <= 0 || elapsedMs >= durationMs) {
		return toLength
	}
	const progress = Math.max(elapsedMs, 0) / durationMs
	let length = start + Math.ceil((toLength - start) * progress)
	// do not cut an emoji or any other character outside the BMP in half
	const lastCode = text.charCodeAt(length - 1)
	if (lastCode >= 0xD800 && lastCode <= 0xDBFF) {
		length++
	}
	return Math.min(length, toLength)
}
