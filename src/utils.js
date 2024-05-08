'use strict'

let mytimer = 0
export function delay(callback, ms = 0) {
	clearTimeout(mytimer)
	mytimer = setTimeout(callback, ms)
}
