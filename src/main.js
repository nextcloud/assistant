import { handleNotification, addAssistantMenuEntry, openAssistantTextProcessingForm, openAssistantTaskResult } from './assistant.js'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

/**
 * - Expose OCA.TpAssistant.openTextProcessingModal to let apps use the assistant
 * - Add a header right menu entry
 * - Listen to notification event
 */
function init() {
	if (!OCA.TpAssistant) {
		/**
		 * @namespace
		 */
		OCA.TPAssistant = {
			openAssistantTextProcessingForm,
			openAssistantTaskResult,
		}
		subscribe('notifications:action:execute', handleNotification)
		if (loadState('assistant', 'assistant-enabled')) {
			addAssistantMenuEntry()
		}
	}
}

init()
