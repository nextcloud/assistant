<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="input-area"
		:class="{
			draggedOver: isDraggedOver,
		}"
		@dragover="onDragOver"
		@dragenter="onDragEnter"
		@dragleave="onDragLeave"
		@drop="onDrop">
		<NcRichContenteditable ref="richContenteditable"
			:class="{ 'input-area__thinking': loading.llmGeneration }"
			class="input"
			:model-value="chatContent"
			:auto-complete="() => {}"
			:link-auto-complete="false"
			:disabled="disabled"
			:placeholder="loading.llmGeneration ? thinkingText : placeholderText"
			:aria-label="loading.llmGeneration ? thinkingText : placeholderText"
			:maxlength="64_000"
			:multiline="isMobile"
			dir="auto"
			@update:model-value="$emit('update:chatContent', $event)"
			@submit="onSubmitText" />
		<div class="input-area__button-box">
			<NcButton v-if="!audioChatAvailable || chatContent"
				class="input-area__button-box__button"
				:aria-label="submitBtnAriaText"
				:disabled="disabled || !chatContent.trim() || chatContentTooLong"
				variant="primary"
				@click="onSubmitText">
				<template #icon>
					<SendIcon :size="20" />
				</template>
			</NcButton>
			<AudioRecorderWrapper v-else
				v-model:is-recording="isRecording"
				:compact="true"
				:disabled="disabled"
				@new-recording="onNewRecording" />
		</div>
	</div>
</template>

<script>
import SendIcon from 'vue-material-design-icons/Send.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'

import AudioRecorderWrapper from '../fields/AudioRecorderWrapper.vue'

import isMobile from '../../mixins/isMobile.js'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { getCurrentUser } from '@nextcloud/auth'
import { uploadInputFile } from '../../utils.js'
import { VALID_TEXT_MIME_TYPES, MAX_TEXT_INPUT_LENGTH } from '../../constants.js'

/*
maxlength calculation (just a rough estimate):
- 1600 characters
- ~400 words
- ~300 tokens
*/

export default {
	name: 'InputArea',

	components: {
		AudioRecorderWrapper,
		SendIcon,
		NcButton,
		NcRichContenteditable,
	},

	mixins: [
		isMobile,
	],

	props: {
		chatContent: {
			type: String,
			required: true,
		},
		loading: {
			type: Object,
			default: () => ({
				initialMessages: false,
				olderMessages: false,
				llmGeneration: false,
				titleGeneration: false,
				newHumanMessage: false,
				newSession: false,
				messageDelete: false,
				sessionDelete: false,
			}),
		},
	},

	emits: [
		'update:chatContent',
		'submit',
		'submit-audio',
	],

	data: () => {
		return {
			placeholderText: t('assistant', 'Type a message…'),
			thinkingText: t('assistant', 'Processing…'),
			submitBtnAriaText: t('assistant', 'Submit'),
			isRecording: false,
			isDraggedOver: false,
			audioChatAvailable: loadState('assistant', 'audio_chat_available', false),
		}
	},

	computed: {
		disabled() {
			return this.loading.llmGeneration || this.loading.olderMessages || this.loading.initialMessages || this.loading.titleGeneration || this.loading.newHumanMessage || this.loading.newSession
		},
		chatContentTooLong() {
			return this.chatContent.length > MAX_TEXT_INPUT_LENGTH
		},
	},

	mounted() {
		this.focus()
	},

	methods: {
		focus() {
			this.$nextTick(() => {
				this.$refs.richContenteditable.focus()
			})
		},
		onNewRecording(blob) {
			const url = generateOcsUrl('/apps/assistant/api/v1/input-file')
			const formData = new FormData()
			formData.append('data', blob)
			formData.append('filename', 'chat-input.wav')
			axios.post(url, formData).then(response => {
				this.$emit('submit-audio', response.data.ocs.data.fileId)
			}).catch(error => {
				showError(
					t('assistant', 'Could not upload the recorded file')
						+ '. ' + t('assistant', 'Please try again and inform the server administrators if this issue persists.'),
				)
				console.error(error)
			})
		},
		onSubmitText(e) {
			if (!this.chatContentTooLong) {
				this.$emit('submit', e)
			}
		},
		onDragOver(e) {
			const fileItems = [...e.dataTransfer.items].filter(
				(item) => item.kind === 'file',
			)
			if (fileItems.length === 1) {
				e.preventDefault()
				e.stopPropagation()
				this.isDraggedOver = true
			}
		},
		onDragEnter(e) {
			const fileItems = [...e.dataTransfer.items].filter(
				(item) => item.kind === 'file',
			)
			if (fileItems.length === 1) {
				e.preventDefault()
				e.stopPropagation()
				this.isDraggedOver = true
			}
		},
		onDragLeave(e) {
			const fileItems = [...e.dataTransfer.items].filter(
				(item) => item.kind === 'file',
			)
			if (fileItems.length === 1) {
				e.preventDefault()
				e.stopPropagation()
				this.isDraggedOver = false
			}
		},
		onDrop(e) {
			e.preventDefault()
			e.stopPropagation()
			const fileItems = [...e.dataTransfer.items].filter(
				(item) => item.kind === 'file',
			)
			if (fileItems.length === 1) {
				this.uploadDroppedFile(fileItems[0].getAsFile())
				this.isDraggedOver = false
			}
		},
		uploadDroppedFile(file) {
			if (!VALID_TEXT_MIME_TYPES.includes(file.type)) {
				showError(t('assistant', 'Invalid file type. Only text files are supported.'))
				return
			}
			this.isUploading = true

			return uploadInputFile(file)
				.then((response) => {
					const data = response.data.ocs.data
					this.onFileUploaded({ fileId: data.fileId, filePath: data.filePath })
				})
				.catch(error => {
					console.error('error while uploading a file after drop', error)
				})
				.then(() => {
					this.isUploading = false
				})
		},
		onFileUploaded({ fileId, filePath }) {
			const userId = getCurrentUser()?.uid
			if (!userId) {
				return
			}
			const userFilePath = filePath.replace('/' + userId + '/files/', '/')
			const url = generateOcsUrl('/apps/assistant/api/v1/parse-file')
			axios.post(url, {
				filePath: userFilePath,
			}).then((response) => {
				const data = response.data?.ocs?.data
				if (data?.parsedText === undefined) {
					showError(t('assistant', 'Unexpected response from text parser'))
					return
				}

				this.$emit('update:chatContent', data?.parsedText)
			}).catch((error) => {
				console.error(error)
				showError(t('assistant', 'Could not parse file'))
			})
		},
	},
}
</script>

<style lang="scss">
[id$='-tribute'][id*='nc-rich-contenteditable-'][role='listbox'] {
	z-index: 9999;
}
</style>

<style lang="scss" scoped>
:deep(.rich-contenteditable) {
	width: 100% !important;

	.rich-contenteditable__input {
		// TODO or fix in nc/vue
		padding-top: 4px !important;
		padding-bottom: 4px !important;

		min-height: var(--default-clickable-area) !important;
		line-height: 22px !important;
	}
}

:deep(.rich-contenteditable__input--disabled) {
	border-radius: var(--border-radius-large) !important;
	cursor: default !important;
}

.input-area {
	display: flex;
	flex-direction: row;
	justify-content: space-between;
	align-items: end;
	gap: 4px;

	&.draggedOver .input {
		border: solid 2px var(--color-border-success);
		border-radius: var(--border-radius-large);
	}

	:deep(&__thinking > div) {
		font-style: italic;
		animation: breathing 2s linear infinite normal;
	}

	&__button-box {
		display: flex;
		flex-direction: column;
		justify-content: end;

		&__button {
			height: fit-content;
		}
	}
}

@keyframes breathing {
	0% {
		border-color: var(--color-main-text);
	}
	50% {
		border-color: var(--color-border-maxcontrast);
	}
	100% {
		border-color: var(--color-main-text);
	}
}
</style>
