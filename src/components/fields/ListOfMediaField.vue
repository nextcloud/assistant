<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="media-list-field">
		<div ref="copyContainer" class="label-row">
			<label class="field-label"
				:title="field.description">
				{{ field.name }}
			</label>
		</div>
		<div v-if="!isOutput"
			class="select-media">
			<UploadInputFileButton
				:accept="acceptedMimeTypes"
				:label="t('assistant', 'Upload from device')"
				:multiple="true"
				:disabled="isRecording || isUploading"
				:is-uploading.sync="isUploading"
				@files-uploaded="onFilesUploaded" />
			<ChooseInputFileButton
				:label="t('assistant', 'Select from Nextcloud')"
				:picker-title="t('assistant', 'Pick one or multiple files')"
				:accept="acceptedMimeTypes"
				:multiple="true"
				:disabled="isRecording || isUploading"
				@files-chosen="onFilesChosen" />
			<AudioRecorderWrapper v-if="isAudioList"
				:disabled="isUploading"
				:is-recording.sync="isRecording"
				@new-recording="onNewRecording" />
		</div>
		<div v-if="value !== null"
			class="media-list">
			<!--div v-for="fileId in value"
				:key="'f' + fileId">
				FILE: {{ fileId }}
			</div-->
			<div v-for="fileId in value"
				:key="fileId"
				class="media-list--item"
				:class="{ row: isAudioList }">
				<component :is="displayComponent"
					:file-id="fileId"
					:is-output="isOutput"
					:clickable="isOutput"
					@delete="onDelete(fileId)"
					@click.native="onPreviewClick(isOutput, fileId)" />
				<div class="buttons">
					<NcButton v-if="!isOutput"
						type="tertiary"
						:aria-label="t('assistant', 'Remove this media')"
						@click="onDelete(fileId)">
						<template #icon>
							<DeleteIcon />
						</template>
					</NcButton>
					<a v-if="isOutput"
						:href="getDownloadUrl(fileId)"
						target="_blank">
						<NcButton :title="t('assistant', 'Download this media')">
							<template #icon>
								<DownloadIcon />
							</template>
						</NcButton>
					</a>
					<NcButton v-if="isOutput"
						:title="t('assistant', 'Save this media')"
						@click="onSave(fileId)">
						<template #icon>
							<ContentSaveIcon />
						</template>
					</NcButton>
					<NcButton v-if="isOutput"
						:title="t('assistant', 'Share this media')"
						@click="onShare(fileId)">
						<template #icon>
							<ShareVariantIcon />
						</template>
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import DeleteIcon from '../icons/DeleteIcon.vue'

import AudioDisplay from './AudioDisplay.vue'
import ImageDisplay from './ImageDisplay.vue'
import FileDisplay from './FileDisplay.vue'
import ChooseInputFileButton from './ChooseInputFileButton.vue'
import UploadInputFileButton from './UploadInputFileButton.vue'
import AudioRecorderWrapper from './AudioRecorderWrapper.vue'

import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import VueClipboard from 'vue-clipboard2'
import Vue from 'vue'

import {
	SHAPE_TYPE_NAMES,
	VALID_VIDEO_MIME_TYPES,
	VALID_IMAGE_MIME_TYPES,
	VALID_AUDIO_MIME_TYPES,
} from '../../constants.js'

Vue.use(VueClipboard)

export default {
	name: 'ListOfMediaField',

	components: {
		AudioRecorderWrapper,
		ChooseInputFileButton,
		UploadInputFileButton,
		DeleteIcon,
		DownloadIcon,
		ShareVariantIcon,
		ContentSaveIcon,
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
			type: [Array, null],
			default: () => [],
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
			isUploading: false,
			isRecording: false,
		}
	},

	computed: {
		isAudioList() {
			return this.field.type === SHAPE_TYPE_NAMES.ListOfAudios
		},
		displayComponent() {
			if (this.field.type === SHAPE_TYPE_NAMES.ListOfImages) {
				return ImageDisplay
			} else if (this.field.type === SHAPE_TYPE_NAMES.ListOfAudios) {
				return AudioDisplay
			/*
			} else if (this.field.type === SHAPE_TYPE_NAMES.ListOfVideo) {
				return VideoDisplay
			*/
			} else if (this.field.type === SHAPE_TYPE_NAMES.ListOfFiles) {
				return FileDisplay
			}
			return null
		},
		acceptedMimeTypes() {
			if (this.field.type === SHAPE_TYPE_NAMES.ListOfImages) {
				return VALID_IMAGE_MIME_TYPES
			} else if (this.field.type === SHAPE_TYPE_NAMES.ListOfAudios) {
				return VALID_AUDIO_MIME_TYPES
			} else if (this.field.type === SHAPE_TYPE_NAMES.ListOfVideos) {
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
		onFilesChosen(files) {
			const fileIds = files.map(f => f.fileid)

			if (this.value === null) {
				this.$emit('update:value', fileIds)
			} else {
				this.$emit('update:value', [...this.value, ...fileIds])
			}
		},
		onFilesUploaded(files) {
			const fileIds = files.map(f => f.fileId)
			if (this.value === null) {
				this.$emit('update:value', fileIds)
			} else {
				this.$emit('update:value', [...this.value, ...fileIds])
			}
		},
		onNewRecording(blob) {
			const url = generateOcsUrl('/apps/assistant/api/v1/input-file')
			const formData = new FormData()
			formData.append('data', blob)
			formData.append('filename', 'recording.mp3')
			axios.post(url, formData).then(response => {
				const fileId = response.data.ocs.data.fileId
				if (this.value === null) {
					this.$emit('update:value', [fileId])
				} else {
					this.$emit('update:value', [...this.value, fileId])
				}
			}).catch(error => {
				showError(t('assistant', 'Could not upload the recorded file'))
				console.error(error)
			})
		},
		onDelete(fileId) {
			if (this.value !== null) {
				this.$emit('update:value', this.value.filter(fid => fid !== fileId))
			}
		},
		getDownloadUrl(fileId) {
			// taskprocessing/tasks/{taskId}/file/{fileId} result has no mimetype
			/*
			return generateOcsUrl('taskprocessing/tasks/{taskId}/file/{fileId}', {
				taskId: this.providedCurrentTaskId(),
				fileId,
			})
			*/
			return generateOcsUrl('apps/assistant/api/v1/task/{taskId}/output-file/{fileId}/download', {
				taskId: this.providedCurrentTaskId(),
				fileId,
			})
		},
		onShare(fileId) {
			if (this.value === null) {
				return
			}

			const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/share', {
				taskId: this.providedCurrentTaskId(),
				fileId,
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
		onSave(fileId) {
			if (this.value === null) {
				return
			}

			const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/save', {
				taskId: this.providedCurrentTaskId(),
				fileId,
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
				const container = this.$refs.copyContainer
				await this.$copyText(content, container)
				showSuccess(message)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Could not copy to clipboard'))
			}
		},
		onPreviewClick(isOutput, fileId) {
			// do not open input media files in the viewer
			if (this.value === null || !isOutput) {
				return
			}

			const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/save', {
				taskId: this.providedCurrentTaskId(),
				fileId,
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
.media-list-field {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;

	.label-row {
		width: 100%;
		display: flex;
		flex-direction: row;
		justify-content: start;
		align-items: center;

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

	.media-list {
		width: 100%;
		display: flex;
		flex-wrap: wrap;
		gap: 8px;

		&--item {
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 8px;
			padding: 12px;
			border-radius: var(--border-radius-large);

			&.row {
				flex-direction: row;
			}

			&:hover {
				background-color: var(--color-primary-element-light-hover);
			}

			.buttons {
				width: 100%;
				display: flex;
				gap: 2px;
				justify-content: start;
			}
		}
	}
}
</style>
