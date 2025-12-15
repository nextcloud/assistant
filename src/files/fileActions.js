/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerFileAction, Permission, FileAction, FileType } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'

import CreationSvgIcon from '@mdi/svg/svg/creation.svg?raw'
import SummarizeSymbol from '@material-symbols/svg-700/outlined/summarize.svg?raw'
import TTSSymbol from '@material-symbols/svg-700/outlined/text_to_speech.svg?raw'
import STTSymbol from '@material-symbols/svg-700/outlined/speech_to_text.svg?raw'
import { VALID_AUDIO_MIME_TYPES, VALID_TEXT_MIME_TYPES } from '../constants.js'

const actionIgnoreLists = [
	'trashbin',
	'files.public',
]

function registerGroupAction(mimeTypes) {
	const groupAction = new FileAction({
		id: 'assistant-group',
		displayName: ({ nodes }) => {
			return t('assistant', 'AI Assistant')
		},
		enabled({ nodes, view }) {
			return !actionIgnoreLists.includes(view.id)
				&& nodes.length === 1
				&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
				&& nodes.every(({ type }) => type === FileType.File)
				&& nodes.every(({ mime }) => mimeTypes.includes(mime))
		},
		iconSvgInline: () => CreationSvgIcon,
		order: 0,
		async exec() {
			return null
		},
	})
	registerFileAction(groupAction)
}

function registerSummarizeAction() {
	const summarizeAction = new FileAction({
		id: 'assistant-summarize',
		parent: 'assistant-group',
		displayName: ({ nodes }) => {
			return t('approval', 'Summarize using AI')
		},
		enabled({ nodes, view }) {
			return !actionIgnoreLists.includes(view.id)
				&& nodes.length === 1
				&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
				&& nodes.every(({ type }) => type === FileType.File)
				&& nodes.every(({ mime }) => VALID_TEXT_MIME_TYPES.includes(mime))
		},
		iconSvgInline: () => SummarizeSymbol,
		order: 0,
		async exec({ nodes }) {
			const node = nodes[0]
			const { default: axios } = await import('@nextcloud/axios')
			const { generateOcsUrl } = await import('@nextcloud/router')
			const { showError, showSuccess } = await import('@nextcloud/dialogs')
			const url = generateOcsUrl('/apps/assistant/api/v1/file-action/{fileId}/core:text2text:summary', { fileId: node.fileid })
			try {
				await axios.post(url)
				showSuccess(
					t('assistant', 'Summarization AI task submitted successfully.') + '\n'
						+ t('assistant', 'You will be notified when it is ready.') + '\n'
						+ t('assistant', 'It can also be checked in the Assistant in the "Work with text -> Summarize" menu.'),
				)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Failed to launch the AI file action'))
			}
			return null
		},
	})
	registerFileAction(summarizeAction)
}

function registerTtsAction() {
	const ttsAction = new FileAction({
		id: 'assistant-tts',
		parent: 'assistant-group',
		displayName: ({ nodes }) => {
			return t('assistant', 'Text-To-Speech using AI')
		},
		enabled({ nodes, view }) {
			return !actionIgnoreLists.includes(view.id)
				&& nodes.length === 1
				&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
				&& nodes.every(({ type }) => type === FileType.File)
				&& nodes.every(({ mime }) => VALID_TEXT_MIME_TYPES.includes(mime))
		},
		iconSvgInline: () => TTSSymbol,
		order: 0,
		async exec({ nodes }) {
			const node = nodes[0]
			const { default: axios } = await import('@nextcloud/axios')
			const { generateOcsUrl } = await import('@nextcloud/router')
			const { showError, showSuccess } = await import('@nextcloud/dialogs')
			const url = generateOcsUrl('/apps/assistant/api/v1/file-action/{fileId}/core:text2speech', { fileId: node.fileid })
			try {
				await axios.post(url)
				showSuccess(
					t('assistant', 'Text-to-Speech AI task submitted successfully.') + '\n'
						+ t('assistant', 'You will be notified when it is ready.') + '\n'
						+ t('assistant', 'It can also be checked in the Assistant in the "Work with audio -> Generate speech" menu.'),
				)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Failed to launch the AI file action'))
			}
			return null
		},
	})
	registerFileAction(ttsAction)
}

function registerSttAction() {
	const sttAction = new FileAction({
		id: 'assistant-stt',
		parent: 'assistant-group',
		displayName: ({ nodes }) => {
			return t('assistant', 'Transcribe audio using AI')
		},
		enabled({ nodes, view }) {
			return !actionIgnoreLists.includes(view.id)
				&& nodes.length === 1
				&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
				&& nodes.every(({ type }) => type === FileType.File)
				&& nodes.every(({ mime }) => VALID_AUDIO_MIME_TYPES.includes(mime))
		},
		iconSvgInline: () => STTSymbol,
		order: 0,
		async exec({ nodes }) {
			const node = nodes[0]
			const { default: axios } = await import('@nextcloud/axios')
			const { generateOcsUrl } = await import('@nextcloud/router')
			const { showError, showSuccess } = await import('@nextcloud/dialogs')
			const url = generateOcsUrl('/apps/assistant/api/v1/file-action/{fileId}/core:audio2text', { fileId: node.fileid })
			try {
				await axios.post(url)
				showSuccess(
					t('assistant', 'Transcription AI task submitted successfully.') + '\n'
						+ t('assistant', 'You will be notified when it is ready.') + '\n'
						+ t('assistant', 'It can also be checked in the Assistant in the "Work with audio -> Transcribe audio" menu.'),
				)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Failed to launch the AI file action'))
			}
			return null
		},
	})
	registerFileAction(sttAction)
}

const assistantEnabled = loadState('assistant', 'assistant-enabled', false)
const summarizeAvailable = loadState('assistant', 'summarize-available', false)
const sttAvailable = loadState('assistant', 'stt-available', false)
const ttsAvailable = loadState('assistant', 'tts-available', false)

if (assistantEnabled) {
	if (summarizeAvailable || sttAvailable || ttsAvailable) {
		const groupMimeTypes = []
		if (summarizeAvailable || ttsAvailable) {
			groupMimeTypes.push(...VALID_TEXT_MIME_TYPES)
		}
		if (sttAvailable) {
			groupMimeTypes.push(...VALID_AUDIO_MIME_TYPES)
		}
		registerGroupAction(groupMimeTypes)
	}
	if (sttAvailable) {
		registerSttAction()
	}
	if (summarizeAvailable) {
		registerSummarizeAction()
	}
	if (ttsAvailable) {
		registerTtsAction()
	}
}
