/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import {
	getRevealLength,
	STREAM_REVEAL_DURATION_MS,
	STREAM_REVEAL_STEP_MS,
} from '../../src/components/ChattyLLM/streamReveal.js'

describe('getRevealLength', () => {
	const text = 'x'.repeat(100)

	it('starts from what is already displayed', () => {
		assert.equal(getRevealLength(text, 40, 0, 200), 40)
	})

	it('reveals the new text linearly over the duration', () => {
		assert.equal(getRevealLength(text, 0, 50, 200), 25)
		assert.equal(getRevealLength(text, 0, 100, 200), 50)
		assert.equal(getRevealLength(text, 40, 100, 200), 70)
	})

	it('displays everything once the duration has elapsed', () => {
		assert.equal(getRevealLength(text, 0, 200, 200), 100)
		assert.equal(getRevealLength(text, 0, 5000, 200), 100)
	})

	it('displays everything when the duration is not positive', () => {
		assert.equal(getRevealLength(text, 0, 0, 0), 100)
		assert.equal(getRevealLength(text, 0, 0, -1), 100)
	})

	it('clamps out of range start lengths', () => {
		assert.equal(getRevealLength(text, -10, 100, 200), 50)
		assert.equal(getRevealLength(text, 150, 0, 200), 100)
		assert.equal(getRevealLength('', 0, 0, 200), 0)
	})

	it('treats a negative elapsed time as zero', () => {
		assert.equal(getRevealLength(text, 10, -50, 200), 10)
	})

	it('never splits a surrogate pair', () => {
		// 'ab' + one emoji (2 UTF-16 code units) + 'cd': length 6
		const emojiText = 'ab\u{1F600}cd'
		assert.equal(emojiText.length, 6)
		// ceil(6 * 0.5) = 3 would end between the two halves of the emoji
		const length = getRevealLength(emojiText, 0, 100, 200)
		assert.equal(length, 4)
		assert.equal(emojiText.slice(0, length), 'ab\u{1F600}')
	})

	it('only grows and stays within the text while time passes', () => {
		const longText = 'Streamed answer with an emoji \u{1F680} and more text. '.repeat(40)
		let previous = 0
		for (let elapsed = 0; elapsed <= STREAM_REVEAL_DURATION_MS + STREAM_REVEAL_STEP_MS; elapsed += STREAM_REVEAL_STEP_MS) {
			const length = getRevealLength(longText, 0, elapsed)
			assert.ok(length >= previous, `length went down at ${elapsed} ms`)
			assert.ok(length <= longText.length)
			const lastCode = longText.charCodeAt(length - 1)
			assert.ok(!(lastCode >= 0xD800 && lastCode <= 0xDBFF), `surrogate pair split at ${elapsed} ms`)
			previous = length
		}
		assert.equal(previous, longText.length)
	})

	it('catches up with a large chunk within the default duration', () => {
		// a 250 ms chunk of a model streaming ~140 tokens/s is roughly 140 characters,
		// a fast model or a 2 s polling fallback can deliver more than 1000 at once
		const chunk = 'y'.repeat(1200)
		assert.ok(STREAM_REVEAL_DURATION_MS < 250)
		assert.equal(getRevealLength(chunk, 0, STREAM_REVEAL_DURATION_MS), chunk.length)
	})
})
