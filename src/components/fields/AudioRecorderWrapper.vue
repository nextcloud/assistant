<template>
	<div class="assistant-audio-recorder-wrapper">
		<NcButton v-if="!isRecording"
			ref="startRecordingButton"
			:disabled="disabled"
			@click="startRecording">
			<template #icon>
				<MicrophoneIcon />
			</template>
			{{ t('assistant', 'Start recording') }}
		</NcButton>
		<!--NcButton v-if="isRecording"
			ref="stopRecordingButton"
			@click="stopRecording">
			<template #icon>
				<StopIcon />
			</template>
			{{ t('assistant', 'Stop recording') }}
		</NcButton-->
		<NcButton v-if="isRecording"
			type="error"
			:title="t('assistant', 'Dismiss recording')"
			@click="cancelRecording">
			<template #icon>
				<CloseIcon />
			</template>
		</NcButton>
		<div v-if="isRecording" class="recording-indicator fadeOutIn" />
		<audio-recorder v-if="!resettingRecorder"
			v-show="isRecording"
			ref="recorder"
			class="recorder"
			:attempts="1"
			:time="300"
			:show-download-button="false"
			:show-upload-button="false"
			:before-recording="onRecordStarts"
			:after-recording="onRecordEnds"
			mode="minimal" />
		<NcButton v-if="isRecording"
			type="success"
			:title="t('assistant', 'End recording and send')"
			@click="stopRecording">
			<template #icon>
				<CheckIcon />
			</template>
		</NcButton>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import MicrophoneIcon from 'vue-material-design-icons/Microphone.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import VueAudioRecorder from 'vue2-audio-recorder'

import Vue from 'vue'
Vue.use(VueAudioRecorder)

export default {
	name: 'AudioRecorderWrapper',

	components: {
		NcButton,
		MicrophoneIcon,
		CheckIcon,
		CloseIcon,
	},

	props: {
		disabled: {
			type: Boolean,
			default: false,
		},
		isRecording: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'new-recording',
	],

	data() {
		return {
			// isRecording: false,
			resettingRecorder: false,
			ignoreNextRecording: false,
		}
	},

	mounted() {
		// const recordButton = this.$refs.startRecordingButton
		// recordButton?.$el?.focus()
	},

	methods: {
		resetRecording() {
			this.ignoreNextRecording = false
			// trick to remove the recorder and re-render it so the data is gone and its state is fresh
			this.resettingRecorder = true
			this.$nextTick(() => {
				this.resettingRecorder = false
				/*
				this.$nextTick(() => {
					const recordButton = this.$refs.startRecordingButton
					recordButton?.$el?.focus()
				})
				*/
			})
		},

		startRecording() {
			this.$refs.recorder.$el.querySelector('.ar-recorder .ar-icon').click()
		},

		stopRecording() {
			this.$refs.recorder.$el.querySelector('.ar-recorder .ar-icon').click()
		},

		cancelRecording() {
			this.ignoreNextRecording = true
			this.stopRecording()
		},

		async onRecordStarts(e) {
			// this.isRecording = true
			this.$emit('update:is-recording', true)
			this.$nextTick(() => {
				const stopButton = this.$refs.stopRecordingButton
				stopButton?.$el?.focus()
			})
		},

		async onRecordEnds(e) {
			// this.isRecording = false
			this.$emit('update:is-recording', false)
			if (!this.ignoreNextRecording) {
				try {
					this.$emit('new-recording', e.blob)
				} catch (error) {
					console.error('Recording error:', error)
					this.$emit('new-recording', null)
				}
			}
			this.resetRecording()
		},
	},
}
</script>

<style scoped lang="scss">
.assistant-audio-recorder-wrapper {
	display: flex;
	align-items: center;
	gap: 10px;

	.recording-indicator {
		width: 16px;
		height: 16px;
		flex: 0 0 16px;
		border-radius: 8px;
		background-color: var(--color-error);
	}

	@keyframes fadeOutIn {
		0% { opacity:1; }
		50% { opacity:.3; }
		100% { opacity:1; }
	}
	.fadeOutIn {
		animation: fadeOutIn 3s infinite;
	}

	:deep(.recorder) {
		max-width: 150px;
		height: 34px;
		width: unset;

		.ar-recorder {
			display: none;
		}
		background-color: var(--color-main-background) !important;
		box-shadow: unset !important;
		.ar-content {
			padding: 0;
			height: 100%;
			display: flex;
			align-items: center;
			justify-content: center;
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
			margin: 0 0 0 0;
			font-size: 20px;
		}
		.ar-recorder__time-limit {
			position: unset !important;
		}
		.ar-player {
			display: none;
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
