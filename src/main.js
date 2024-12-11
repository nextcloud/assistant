/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	handleNotification, addAssistantMenuEntry,
	openAssistantForm,
	openAssistantTask,
} from './assistant.js'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

/**
 * - Expose OCA.Assistant.openAssistantForm to let apps use the assistant
 * - Add a header right menu entry
 * - Listen to notification event
 */
function init() {
	if (!OCA.Assistant) {
		/**
		 * @namespace
		 */
		OCA.Assistant = {
			openAssistantForm,
			openAssistantTask,
		}
		// to keep backward compatibility (with apps that already integrate the assistant, like Text)
		OCA.TPAssistant = OCA.Assistant

		subscribe('notifications:action:execute', handleNotification)
		if (loadState('assistant', 'assistant-enabled')) {
			addAssistantMenuEntry()
			OCA.Assistant.last_target_language = loadState('assistant', 'last-target-language')
		}
	}
}

init()
