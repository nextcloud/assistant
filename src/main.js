import {
	handleNotification, addAssistantMenuEntry,
	openAssistantForm, openAssistantTextProcessingForm,
	openAssistantTaskResult,
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
			openAssistantTextProcessingForm,
			openAssistantTaskResult,
		}
		// to keep backward compatibility (with apps that already integrate the assistant, like Text)
		OCA.TPAssistant = OCA.Assistant

		subscribe('notifications:action:execute', handleNotification)
		if (loadState('assistant', 'assistant-enabled')) {
			addAssistantMenuEntry()
		}
	}
}

init()
