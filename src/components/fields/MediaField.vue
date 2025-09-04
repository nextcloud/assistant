<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="media-field">
		<div class="label-row">
			<label class="field-label"
				:title="field.description">
				{{ field.name }}
			</label>
		</div>
		<div v-if="!isOutput"
			class="select-media">
			<UploadInputFileButton
				v-model:is-uploading="isUploading"
				:accept="acceptedMimeTypes"
				:label="t('assistant', 'Upload from device')"
				:disabled="value !== null || isRecording || isUploading"
				@files-uploaded="onFileUploaded" />
			<ChooseInputFileButton
				:label="t('assistant', 'Select from storage')"
				:picker-title="t('assistant', 'Pick a file')"
				:accept="acceptedMimeTypes"
				:disabled="value !== null || isRecording || isUploading"
				@files-chosen="onFileChosen" />
			<AudioRecorderWrapper v-if="isAudio"
				v-model:is-recording="isRecording"
				:disabled="value !== null || isUploading"
				@new-recording="onNewRecording" />
		</div>
		<div v-if="value !== null"
			class="media-value"
			:class="{ row: isAudio }">
			<!--div>
				FILE: {{ value }} PATH: {{ filePath }}
			</div-->
			<component :is="displayComponent"
				:file-id="value"
				:task-id="providedCurrentTaskId()"
				:show-delete="false"
				:is-output="isOutput"
				:clickable="true"
				@click.native="onPreviewClick" />
			<div v-if="isOutput"
				class="buttons">
				<a :href="getDownloadUrl()"
					target="_blank">
					<NcButton :title="t('assistant', 'Download this media')">
						<template #icon>
							<TrayArrowDownIcon />
						</template>
					</NcButton>
				</a>
				<NcButton
					:title="t('assistant', 'Save this media')"
					@click="onSave">
					<template #icon>
						<ContentSaveOutlineIcon />
					</template>
				</NcButton>
				<NcButton
					:title="t('assistant', 'Share this media')"
					@click="onShare">
					<template #icon>
						<ShareVariantIcon />
					</template>
				</NcButton>
			</div>
			<div v-else
				class="buttons">
				<NcButton
					variant="tertiary"
					:title="t('assistant', 'Clear value')"
					@click="onClear">
					<template #icon>
						<CloseIcon />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import TrayArrowDownIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import AudioDisplay from './AudioDisplay.vue'
import ImageDisplay from './ImageDisplay.vue'
// import VideoDisplay from './VideoDisplay.vue'
import FileDisplay from './FileDisplay.vue'
import AudioRecorderWrapper from './AudioRecorderWrapper.vue'
import UploadInputFileButton from './UploadInputFileButton.vue'
import ChooseInputFileButton from './ChooseInputFileButton.vue'

import { SHAPE_TYPE_NAMES, VALID_AUDIO_MIME_TYPES, VALID_IMAGE_MIME_TYPES, VALID_VIDEO_MIME_TYPES } from '../../constants.js'

import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'MediaField',

	components: {
		AudioRecorderWrapper,
		ChooseInputFileButton,
		UploadInputFileButton,
		TrayArrowDownIcon,
		ShareVariantIcon,
		CloseIcon,
		ContentSaveOutlineIcon,
		NcButton,
	},

	inject: [
		'providedCurrentTaskId',
	],

	props: {
		fieldKey: {
			type: String,
			required: true,
		},
		value: {
			type: [Number, null],
			default: null,
		},
		field: {
			type: Object,
			required: true,
		},
		isOutput: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:value',
	],

	data() {
		return {
			filePath: null,
			isUploading: false,
			isRecording: false,
		}
	},

	computed: {
		isAudio() {
			return this.field.type === SHAPE_TYPE_NAMES.Audio
		},
		displayComponent() {
			if (this.field.type === SHAPE_TYPE_NAMES.Image) {
				return ImageDisplay
			} else if (this.field.type === SHAPE_TYPE_NAMES.Audio) {
				return AudioDisplay
			} else if (this.field.type === SHAPE_TYPE_NAMES.File) {
				return FileDisplay
			/*
			} else if (this.field.type === SHAPE_TYPE_NAMES.Video) {
				return VideoDisplay
			*/
			}
			return null
		},
		acceptedMimeTypes() {
			if (this.field.type === SHAPE_TYPE_NAMES.Image) {
				return VALID_IMAGE_MIME_TYPES
			} else if (this.field.type === SHAPE_TYPE_NAMES.Audio) {
				return VALID_AUDIO_MIME_TYPES
			} else if (this.field.type === SHAPE_TYPE_NAMES.Video) {
				return VALID_VIDEO_MIME_TYPES
			}
			return undefined
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onNewRecording(blob) {
			const url = generateOcsUrl('/apps/assistant/api/v1/input-file')
			const formData = new FormData()
			formData.append('data', blob)
			formData.append('filename', 'recording.mp3')
			axios.post(url, formData).then(response => {
				this.$emit('update:value', response.data.ocs.data.fileId)
				this.filePath = response.data.ocs.data.filePath
			}).catch(error => {
				showError(t('assistant', 'Could not upload the recorded file'))
				console.error(error)
			})
		},
		onFileUploaded(data) {
			this.$emit('update:value', data.fileId)
			this.filePath = data.filePath
		},
		onFileChosen(file) {
			this.filePath = file.path
			this.$emit('update:value', file.fileid)
		},
		onClear() {
			this.$emit('update:value', null)
		},
		getDownloadUrl() {
			// taskprocessing/tasks/{taskId}/file/{fileId} result has no mimetype
			/*
			return generateOcsUrl('taskprocessing/tasks/{taskId}/file/{fileId}', {
				taskId: this.providedCurrentTaskId(),
				fileId: this.value,
			})
			*/
			return generateOcsUrl('apps/assistant/api/v1/task/{taskId}/output-file/{fileId}/download', {
				taskId: this.providedCurrentTaskId(),
				fileId: this.value,
			})
		},
		onShare() {
			if (this.value === null) {
				return
			}

			const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/share', {
				taskId: this.providedCurrentTaskId(),
				fileId: this.value,
			})
			axios.post(url).then(response => {
				const shareToken = response.data.ocs.data.shareToken
				const shareUrl = window.location.protocol + '//' + window.location.host + generateUrl('/s/{shareToken}', { shareToken })
				console.debug('[assistant] generated share link', shareUrl)
				const message = t('assistant', 'Output file share link copied to clipboard')
				this.copyString(shareUrl, message)
			}).catch(error => {
				console.error(error)
			})
		},
		onSave() {
			if (this.value === null) {
				return
			}

			const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/save', {
				taskId: this.providedCurrentTaskId(),
				fileId: this.value,
			})
			return axios.post(url).then(response => {
				const savedPath = response.data.ocs.data.path
				const savedFileId = response.data.ocs.data.fileId
				console.debug('[assistant] save output file', savedPath)

				const directUrl = window.location.protocol + '//' + window.location.host + generateUrl('/f/{savedFileId}', { savedFileId })
				const openMessage = `<a href="${directUrl}" target="_blank">${t('assistant', 'Click this to open the file')}</a>`
				showSuccess(openMessage, { isHTML: true })

				const afterCopyMessage = t('assistant', 'This output file has been saved in {path}', { path: savedPath })
				this.copyString(directUrl, afterCopyMessage)
			}).catch(error => {
				console.error(error)
			})
		},
		async copyString(content, message) {
			try {
				await navigator.clipboard.writeText(content)
				showSuccess(message)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Could not copy to clipboard'))
			}
		},
		onPreviewClick() {
			if (this.value === null) {
				return
			}

			const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/save', {
				taskId: this.providedCurrentTaskId(),
				fileId: this.value,
			})
			return axios.post(url).then(response => {
				const savedPath = response.data.ocs.data.path
				console.debug('[assistant] view output file', savedPath)
				OCA.Viewer.open({ path: savedPath })
			}).catch(error => {
				console.error(error)
			})
		},
	},
}
</script>

<style lang="scss">
.media-field {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 12px;

	.label-row {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: start;

		.field-label {
			font-weight: bold;
		}
	}

	.select-media {
		width: 100%;
		display: flex;
		align-items: start;
		gap: 8px;
	}

	.media-value {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 4px;
		padding: 12px;
		border-radius: var(--border-radius-large);

		&.row {
			flex-direction: row;
		}

		&:hover {
			background-color: var(--color-primary-element-light-hover);
		}

		.buttons {
			display: flex;
			gap: 2px;
			justify-content: center;
		}
	}
}
</style>
