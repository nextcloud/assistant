<template>
	<div class="assistant-audio-recorder">
		<div class="recorder-wrapper"
			:class="{horizontal: inline}">
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
				v-show="isRecording"
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
	</div>
</template>

<script>
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'
import MicrophoneIcon from 'vue-material-design-icons/Microphone.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import VueAudioRecorder from 'vue2-audio-recorder'

import Vue from 'vue'
Vue.use(VueAudioRecorder)

export default {
	name: 'AudioRecorderWrapper',

	components: {
		NcButton,
		UndoIcon,
		MicrophoneIcon,
		StopIcon,
	},

	props: {
		inline: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'new-recording',
	],

	data() {
		return {
			audioData: null,
			isRecording: false,
			resettingRecorder: false,
		}
	},

	mounted() {
		// const recordButton = this.$refs.startRecordingButton
		// recordButton?.$el?.focus()
	},

	methods: {
		resetAudioState() {
			this.isRecording = false
		},

		resetRecording() {
			this.audioData = null
			// this.$emit('update:audio-data', null)
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
				this.$emit('new-recording', e.blob)
			} catch (error) {
				console.error('Recording error:', error)
				this.$emit('new-recording', null)
			}
			this.resetRecording()
		},
	},
}
</script>

<style scoped lang="scss">
.assistant-audio-recorder {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	.recorder-wrapper {
		display: flex;
		flex-direction: column;
		align-items: center;
		&.horizontal {
			flex-direction: row;
		}
	}

	:deep(.recorder) {
		max-width: 150px;
		height: 34px;
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
