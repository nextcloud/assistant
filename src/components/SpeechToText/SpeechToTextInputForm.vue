<template>
	<div class="stt-input-form">
		<div class="form-wrapper">
			<div class="line justified">
				<div class="radios">
					<NcCheckboxRadioSwitch
						:button-variant="true"
						:checked="mode"
						type="radio"
						value="record"
						button-variant-grouped="horizontal"
						name="mode"
						@update:checked="onModeChanged">
						{{ t('assistant', 'Record Audio') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						:button-variant="true"
						:checked="mode"
						type="radio"
						value="choose"
						button-variant-grouped="horizontal"
						name="mode"
						@update:checked="onModeChanged">
						{{ t('assistant', 'Choose Audio File') }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>
		</div>
		<div v-show="mode === 'record'"
			class="recorder-wrapper">
			<NcButton v-if="audioData !== null"
				:aria-label="t('assistant', 'Reset recorded audio')"
				@click="resetRecording">
				<template #icon>
					<UndoIcon />
				</template>
				{{ t('assistant', 'Reset') }}
			</NcButton>
			<NcButton v-if="audioData === null && !isRecording"
				ref="startRecordingButton"
				@click="startRecording">
				<template #icon>
					<MicrophoneIcon />
				</template>
				{{ t('assistant', 'Start recording') }}
			</NcButton>
			<NcButton v-if="audioData === null && isRecording"
				ref="stopRecordingButton"
				@click="stopRecording">
				<template #icon>
					<StopIcon />
				</template>
				{{ t('assistant', 'Stop recording') }}
			</NcButton>
			<audio-recorder v-if="!resettingRecorder"
				ref="recorder"
				class="recorder"
				:class="{'no-audio': audioData === null, 'with-audio': audioData !== null}"
				:attempts="1"
				:time="300"
				:show-download-button="false"
				:show-upload-button="false"
				:before-recording="onRecordStarts"
				:after-recording="onRecordEnds"
				mode="minimal" />
		</div>
		<div v-show="mode === 'choose'">
			<div class="line">
				{{ audioFilePath == null
					? t('assistant', 'No audio file selected')
					: t('assistant', 'Selected Audio File:') + " " + audioFilePath.split('/').pop() }}
			</div>
			<div class="line justified">
				<NcButton
					:disabled="loading"
					:aria-label="t('assistant', 'Choose audio file in your storage')"
					@click="onChooseButtonClick">
					{{ t('assistant', 'Choose audio File') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'
import MicrophoneIcon from 'vue-material-design-icons/Microphone.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import VueAudioRecorder from 'vue2-audio-recorder'

import { getFilePickerBuilder } from '@nextcloud/dialogs'

import Vue from 'vue'
Vue.use(VueAudioRecorder)

const VALID_MIME_TYPES = [
	'audio/mpeg',
	'audio/mp4',
	'audio/ogg',
	'audio/wav',
	'audio/x-wav',
	'audio/webm',
	'audio/opus',
	'audio/flac',
	'audio/vorbis',
	'audio/m4b',
]

const picker = getFilePickerBuilder(t('assistant', 'Choose Audio File'))
	.setMimeTypeFilter(VALID_MIME_TYPES)
	.setMultiSelect(false)
	.allowDirectories(false)
	.setType(1)
	.build()

export default {
	name: 'SpeechToTextInputForm',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		UndoIcon,
		MicrophoneIcon,
		StopIcon,
	},

	props: {
		loading: {
			type: Boolean,
			default: false,
		},
		mode: {
			type: String,
			default: 'record',
		},
		audioData: {
			type: [Blob, null],
			default: null,
		},
		audioFilePath: {
			type: [String, null],
			default: null,
		},
	},

	data() {
		return {
			isRecording: false,
			resettingRecorder: false,
		}
	},

	mounted() {
		const recordButton = this.$refs.startRecordingButton
		recordButton?.$el?.focus()
	},

	methods: {
		resetAudioState() {
			this.$emit('update:audio-data', null)
			this.$emit('update:audio-file-path', null)
			this.isRecording = false
		},

		onModeChanged(newValue) {
			if (this.isRecording) {
				this.stopRecording()
			}
			this.$emit('update:mode', newValue)
		},

		async onChooseButtonClick() {
			this.$emit('update:audio-file-path', await picker.pick())
		},

		resetRecording() {
			this.$emit('update:audio-data', null)
			// trick to remove the recorder and re-render it so the data is gone and its state is fresh
			this.resettingRecorder = true
			this.$nextTick(() => {
				this.resettingRecorder = false
				this.$nextTick(() => {
					const recordButton = this.$refs.startRecordingButton
					recordButton?.$el?.focus()
				})
			})
		},

		startRecording() {
			this.$refs.recorder.$el.querySelector('.ar-recorder .ar-icon').click()
		},

		stopRecording() {
			this.$refs.recorder.$el.querySelector('.ar-recorder .ar-icon').click()
		},

		async onRecordStarts(e) {
			this.isRecording = true
			this.$nextTick(() => {
				const stopButton = this.$refs.stopRecordingButton
				stopButton?.$el?.focus()
			})
		},

		async onRecordEnds(e) {
			this.isRecording = false
			try {
				this.$emit('update:audio-data', e.blob)
			} catch (error) {
				console.error('Recording error:', error)
				this.$emit('update:audio-data', null)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.stt-input-form {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	//padding: 12px 16px 16px 16px;

	.form-wrapper {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 100%;
		margin: 8px 0;
		.radios {
			display: flex;

			:deep(.checkbox-radio-switch__text) {
				flex: unset !important;
			}
		}
	}

	.line {
		display: flex;
		align-items: center;
		margin-top: 8px;
		width: 100%;
		&.justified {
			justify-content: center;
		}
	}

	.recorder-wrapper {
		margin: 12px 0 12px 0;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	:deep(.recorder) {
		&.no-audio {
			.ar-player {
				&-actions {
					display: none;
				}
			}
		}

		.ar-recorder {
			display: none;
		}
		margin-top: 2px;
		background-color: var(--color-main-background) !important;
		box-shadow: unset !important;
		.ar-content {
			padding: 0;
		}
		.ar-content * {
			color: var(--color-main-text) !important;
		}
		.ar-icon {
			background-color: var(--color-main-background) !important;
			fill: var(--color-main-text) !important;
			border: 1px solid var(--color-border) !important;
		}
		.ar-recorder__duration {
			margin: 16px 0 16px 0;
		}
		.ar-recorder__time-limit {
			position: unset !important;
		}
		.ar-player {
			&-bar {
				border: 1px solid var(--color-border) !important;
			}
			.ar-line-control {
				background-color: var(--color-background-dark) !important;
				&__head {
					background-color: var(--color-main-text) !important;
				}
			}
			&__time {
				font-size: 14px;
			}
			.ar-volume {
				&__icon {
					background-color: var(--color-main-background) !important;
					fill: var(--color-main-text) !important;
				}
			}
		}
		.ar-records {
			height: unset !important;
			&__record {
				border-bottom: 1px solid var(--color-border) !important;
			}
			&__record--selected {
				background-color: var(--color-background-dark) !important;
				border: 1px solid var(--color-border) !important;
				.ar-icon {
					background-color: var(--color-background-dark) !important;
				}
			}
		}
	}
}
</style>
