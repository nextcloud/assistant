import { handleNotification, addAssistantMenuEntry, openAssistantForm } from './assistant.js'
import { subscribe } from '@nextcloud/event-bus'

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
		}
		subscribe('notifications:action:execute', handleNotification)
		addAssistantMenuEntry()
	}
}

init()
