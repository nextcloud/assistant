<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="assistant-audio-recorder-wrapper">
		<NcButton v-if="!isRecording"
			ref="startRecordingButton"
			:disabled="disabled"
			@click="start">
			<template #icon>
				<MicrophoneOutlineIcon />
			</template>
			{{ t('assistant', 'Start recording') }}
		</NcButton>
		<NcButton v-if="isRecording"
			variant="error"
			:title="t('assistant', 'Dismiss recording')"
			@click="abortRecording">
			<template #icon>
				<CloseIcon />
			</template>
		</NcButton>
		<div v-if="isRecording" class="recording">
			<div class="recording--indicator fadeOutIn" />
			<span class="time">
				{{ parsedRecordTime }}
			</span>
		</div>
		<NcButton v-if="isRecording"
			variant="success"
			:title="t('assistant', 'End recording and send')"
			@click="stop">
			<template #icon>
				<CheckIcon />
			</template>
		</NcButton>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import MicrophoneOutlineIcon from 'vue-material-design-icons/MicrophoneOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import { showError } from '@nextcloud/dialogs'

import { convertWavToMp3 } from '../../audioUtils.js'
import { MediaRecorder, register } from 'extendable-media-recorder'
import { connect } from 'extendable-media-recorder-wav-encoder'

/**
 * Slightly simpler than the talk NewMessageAudioRecorder
 */
export default {
	name: 'AudioRecorderWrapper',

	components: {
		NcButton,
		MicrophoneOutlineIcon,
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
		'update:is-recording',
	],

	data() {
		return {
			// The audio stream object
			audioStream: null,
			// The media recorder which generate the recorded chunks
			mediaRecorder: null,
			// The chunks array
			chunks: [],
			// Switched to true if the recording is aborted
			aborted: false,
			// recordTimer
			recordTimer: null,
			// the record timer
			recordTime: {
				minutes: 0,
				seconds: 0,
			},
		}
	},

	computed: {
		parsedRecordTime() {
			const seconds = this.recordTime.seconds.toString().length === 2 ? this.recordTime.seconds : `0${this.recordTime.seconds}`
			const minutes = this.recordTime.minutes.toString().length === 2 ? this.recordTime.minutes : `0${this.recordTime.minutes}`
			return `${minutes}:${seconds}`
		},
	},

	watch: {
		isRecording(newValue) {
			console.debug('isRecording', newValue)
		},
	},

	mounted() {
	},

	beforeUnmount() {
		this.killStreams()
	},

	methods: {
		async start() {
			if (!OCA.Assistant.encoderRegistered) {
				await register(await connect())
				OCA.Assistant.encoderRegistered = true
			}
			const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
			this.mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/wav' })

			// Add event handler to onstop
			this.mediaRecorder.onstop = this.generateFile

			// Add event handler to ondataavailable
			this.mediaRecorder.ondataavailable = (e) => {
				this.chunks.push(e.data)
			}

			try {
				// Start the recording
				this.mediaRecorder.start()
			} catch (exception) {
				console.debug(exception)
				this.aborted = true
				this.stop()
				this.killStreams()
				this.resetComponentData()
				showError(t('assistant', 'Error while recording audio'))
				return
			}

			console.debug(this.mediaRecorder.state)

			// Start the timer
			this.recordTimer = setInterval(() => {
				if (this.recordTime.seconds === 59) {
					this.recordTime.minutes++
					this.recordTime.seconds = 0
				}
				this.recordTime.seconds++
			}, 1000)
			// Forward an event to let the parent NewMessage component
			// that there's an undergoing recording operation
			this.$emit('update:is-recording', true)
		},

		stop() {
			this.mediaRecorder.stop()
			clearInterval(this.recordTimer)
			this.$emit('update:is-recording', false)
		},

		/**
		 * Generate the file
		 */
		async generateFile() {
			this.killStreams()
			if (!this.aborted) {
				const wavBlob = new Blob(this.chunks, { type: this.mediaRecorder.mimeType })
				const mp3Blob = await convertWavToMp3(wavBlob)
				this.$emit('new-recording', mp3Blob)
				this.$emit('update:is-recording', false)
			}
			this.resetComponentData()
		},

		/**
		 * Aborts the recording operation.
		 */
		abortRecording() {
			this.aborted = true
			this.stop()
		},

		/**
		 * Resets this component to its initial state
		 */
		resetComponentData() {
			this.audioStream = null
			this.mediaRecorder = null
			this.chunks = []
			this.aborted = false
			this.recordTime = {
				minutes: 0,
				seconds: 0,
			}
		},

		/**
		 * Stop the audio streams
		 */
		killStreams() {
			this.audioStream?.getTracks().forEach(track => track.stop())
		},
	},
}
</script>

<style scoped lang="scss">
.assistant-audio-recorder-wrapper {
	display: flex;
	align-items: center;
	gap: 10px;

	.recording {
		display: flex;
		align-items: center;
		gap: 8px;

		&--indicator {
			width: 16px;
			height: 16px;
			flex: 0 0 16px;
			border-radius: 8px;
			background-color: var(--color-error);
		}

		@keyframes fadeOutIn {
			0% {
				opacity: 1;
			}
			50% {
				opacity: .3;
			}
			100% {
				opacity: 1;
			}
		}

		.fadeOutIn {
			animation: fadeOutIn 3s infinite;
		}
	}
}
</style>
