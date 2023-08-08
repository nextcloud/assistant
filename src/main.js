import { handleNotification, addAssistantMenuEntry, openAssistantForm, openAssistantResult } from './assistant.js'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

/**
 * - Expose OCA.TPAssistant.openTextProcessingModal to let apps use the assistant
 * - Add a header right menu entry
 * - Listen to notification event
 */
function init() {
	if (!OCA.TPAssistant) {
		/**
		 * @namespace
		 */
		OCA.TPAssistant = {
			openAssistantForm,
			openAssistantResult,
		}
		subscribe('notifications:action:execute', handleNotification)
		if (loadState('textprocessing_assistant', 'assistant-enabled')) {
			addAssistantMenuEntry()
		}
	}
}

init()
