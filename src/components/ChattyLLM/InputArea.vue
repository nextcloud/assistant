<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="input-area">
		<DocumentPreviews v-if="multimodalChatAvailable && attachedFileIds.length > 0"
			:file-ids="attachedFileIds"
			@delete="onDeleteAttachedFile" />
		<div class="input-area__row">
			<input v-if="multimodalChatAvailable"
				ref="fileInput"
				type="file"
				multiple
				style="display: none;"
				@change="onUploadFileSelected">
			<NcActions v-if="multimodalChatAvailable"
				class="input-area__attach"
				:aria-label="attachFileAriaText"
				:disabled="disabled || isUploading">
				<NcActionButton :close-after-click="true"
					:disabled="isUploading"
					@click="onUploadFromDevice">
					<template #icon>
						<UploadOutlineIcon :size="20" />
					</template>
					{{ uploadFromDeviceText }}
				</NcActionButton>
				<NcActionButton :close-after-click="true"
					:disabled="isUploading"
					@click="onChooseFromNextcloud">
					<template #icon>
						<FolderPlusOutlineIcon :size="20" />
					</template>
					{{ chooseFromNextcloudText }}
				</NcActionButton>
				<template #icon>
					<NcLoadingIcon v-if="isUploading" :size="20" />
					<PlusIcon v-else :size="20" />
				</template>
			</NcActions>
			<NcRichContenteditable ref="richContenteditable"
				:class="{ 'input-area__thinking': loading.llmGeneration }"
				:model-value="chatContent"
				:auto-complete="() => {}"
				:link-auto-complete="false"
				:disabled="disabled"
				:placeholder="placeholder"
				:aria-label="placeholder"
				:maxlength="64_000"
				:multiline="isMobile"
				dir="auto"
				@paste="onPaste"
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
	</div>
</template>

<script>
import FolderPlusOutlineIcon from 'vue-material-design-icons/FolderPlusOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import SendIcon from 'vue-material-design-icons/Send.vue'
import UploadOutlineIcon from 'vue-material-design-icons/UploadOutline.vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'

import AudioRecorderWrapper from '../fields/AudioRecorderWrapper.vue'
import DocumentPreviews from './DocumentPreviews.vue'

import isMobile from '../../mixins/isMobile.js'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { MAX_ATTACHED_FILES, MAX_TEXT_INPUT_LENGTH } from '../../constants.js'
import { uploadInputFile } from '../../utils.js'

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
		DocumentPreviews,
		FolderPlusOutlineIcon,
		PlusIcon,
		SendIcon,
		UploadOutlineIcon,
		NcActionButton,
		NcActions,
		NcButton,
		NcLoadingIcon,
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

	data() {
		return {
			placeholderText: t('assistant', 'Type a message…'),
			thinkingText: t('assistant', 'Processing…'),
			scheduledText: t('assistant', 'Waiting…'),
			submitBtnAriaText: t('assistant', 'Submit'),
			attachFileAriaText: t('assistant', 'Attach a file'),
			uploadFromDeviceText: t('assistant', 'Upload from device'),
			chooseFromNextcloudText: t('assistant', 'Select from nextcloud'),
			isRecording: false,
			isUploading: false,
			attachedFileIds: [],
			audioChatAvailable: loadState('assistant', 'audio_chat_available', false),
			multimodalChatAvailable: loadState('assistant', 'multimodal_chat_available', false),
			picker: (callback) => getFilePickerBuilder(t('assistant', 'Choose a file'))
				.setMultiSelect(true)
				.allowDirectories(false)
				.addButton({
					id: 'choose-input-file',
					label: t('assistant', 'Choose'),
					variant: 'primary',
					callback: callback(),
				})
				.build(),
		}
	},

	computed: {
		disabled() {
			return this.loading.llmGeneration || this.loading.olderMessages || this.loading.initialMessages || this.loading.titleGeneration || this.loading.newHumanMessage || this.loading.newSession
		},
		chatContentTooLong() {
			return this.chatContent.length > MAX_TEXT_INPUT_LENGTH && this.attachedFileIds.length > MAX_ATTACHED_FILES
		},
		placeholder() {
			return this.loading.llmGeneration
				? this.loading.llmRunning
					? this.thinkingText
					: this.scheduledText
				: this.placeholderText
		},
	},

	mounted() {
		this.focus()
	},

	methods: {
		onPaste(e) {
			if (!this.multimodalChatAvailable || e.clipboardData.files.length === 0) {
				return
			}
			e.preventDefault()
			this.uploadFiles(e.clipboardData.files)
		},
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
				this.$emit('submit', this.attachedFileIds)
				this.attachedFileIds = []
			}
		},
		onUploadFromDevice() {
			this.$refs.fileInput.click()
		},
		onUploadFileSelected() {
			this.uploadFiles(this.$refs.fileInput.files)
				.finally(() => {
					this.$refs.fileInput.value = ''
				})
		},
		uploadFiles(files) {
			if (!files || files.length === 0) {
				return Promise.resolve()
			}
			this.isUploading = true
			return Promise.all(Array.from(files).map(f => uploadInputFile(f)))
				.then((responses) => {
					const fileIds = responses.map(response => response.data.ocs.data.fileId)
					this.attachedFileIds = [...this.attachedFileIds, ...fileIds]
				})
				.catch((error) => {
					showError(t('assistant', 'Could not upload the file'))
					console.error(error)
				})
				.finally(() => {
					this.isUploading = false
				})
		},
		async onChooseFromNextcloud() {
			await this.picker(this.pickerSubmitted).pick()
		},
		pickerSubmitted() {
			return (nodes) => {
				if (!nodes || nodes.length === 0 || !nodes[0].path) {
					showError(t('assistant', 'No file selected'))
					return
				}
				const fileIds = nodes.map(node => node.fileid)
				this.attachedFileIds = Array.from(new Set([...this.attachedFileIds, ...fileIds]))
			}
		},
		onDeleteAttachedFile(fileId) {
			this.attachedFileIds = this.attachedFileIds.filter(id => id !== fileId)
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
	flex-direction: column;
	gap: 4px;

	&__row {
		display: flex;
		flex-direction: row;
		align-items: end;
		gap: 4px;
	}

	:deep(&__thinking > div) {
		font-style: italic;
		animation: breathing 2s linear infinite normal;
	}
	@media (prefers-reduced-motion: reduce) {
		:deep(&__thinking > div) {
			animation: none;
		}
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
