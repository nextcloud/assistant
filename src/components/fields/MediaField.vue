<template>
	<div class="media-field">
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
				:label="t('assistant', 'Upload a file')"
				@files-uploaded="onFileUploaded" />
			<ChooseInputFileButton
				:label="t('assistant', 'Choose a file')"
				:picker-title="t('assistant', 'Choose a file')"
				:accept="acceptedMimeTypes"
				@files-chosen="onFileChosen" />
			<AudioRecorderWrapper v-if="isAudio"
				:inline="true"
				@new-recording="onNewRecording" />
		</div>
		<div v-if="value !== null"
			class="media-value">
			<!--div>
				FILE: {{ value }} PATH: {{ filePath }}
			</div-->
			<component :is="displayComponent"
				:file-id="value"
				:show-delete="false"
				:is-output="isOutput" />
			<div v-if="isOutput"
				class="buttons">
				<a :href="getDownloadUrl()"
					target="_blank">
					<NcButton :title="t('assistant', 'Download this media')">
						<template #icon>
							<DownloadIcon />
						</template>
					</NcButton>
				</a>
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
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

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
import VueClipboard from 'vue-clipboard2'
import Vue from 'vue'

Vue.use(VueClipboard)

export default {
	name: 'MediaField',

	components: {
		AudioRecorderWrapper,
		ChooseInputFileButton,
		UploadInputFileButton,
		DownloadIcon,
		ShareVariantIcon,
		CloseIcon,
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
			return generateOcsUrl('taskprocessing/tasks/{taskId}/file/{fileId}', {
				taskId: this.providedCurrentTaskId(),
				fileId: this.value,
			})
		},
		onShare() {
			if (this.value !== null) {
				const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/share', {
					taskId: this.providedCurrentTaskId(),
					fileId: this.value,
				})
				axios.post(url).then(response => {
					const shareToken = response.data.ocs.data.shareToken
					const shareUrl = window.location.protocol + '//' + window.location.host + generateUrl('/s/{shareToken}', { shareToken })
					console.debug('aaaa share link', shareUrl)
					const message = t('assistant', 'Output file share link copied to clipboard')
					this.copyString(shareUrl, message)
				}).catch(error => {
					console.error(error)
				})
			}
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
	},
}
</script>

<style lang="scss">
.media-field {
	display: flex;
	flex-direction: column;
	align-items: center;
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
		width: 100%;
		display: flex;
		align-items: center;
		gap: 4px;

		.buttons {
			display: flex;
			flex-direction: column;
			gap: 2px;
			justify-content: center;
		}
	}
}
</style>
