<template>
	<div class="picker-content-wrapper">
		<div class="picker-content">
			<h2>
				<AssistantIcon :size="24" class="icon" />
				{{ t('assistant', 'Audio transcription') }}
			</h2>
			<SpeechToTextInputForm
				:mode.sync="mode"
				:audio-data.sync="audioData"
				:audio-file-path.sync="audioFilePath"
				:loading="loading" />
			<div class="footer">
				<NcButton
					type="primary"
					:disabled="loading || !canSubmit"
					@click="onInputEnter">
					<template #icon>
						<NcLoadingIcon v-if="loading"
							:size="20" />
						<ArrowRightIcon v-else />
					</template>
					{{ t('assistant', 'Schedule Transcription') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'

import AssistantIcon from '../../components/icons/AssistantIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

import SpeechToTextInputForm from '../../components/SpeechToText/SpeechToTextInputForm.vue'

export default {
	name: 'SpeechToTextCustomPickerElement',

	components: {
		SpeechToTextInputForm,
		ArrowRightIcon,
		NcButton,
		NcLoadingIcon,
		AssistantIcon,
	},

	props: {
		providerId: {
			type: String,
			required: true,
		},
		accessible: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			loading: false,
			mode: 'record',
			audioData: null,
			audioFilePath: null,
		}
	},

	computed: {
		canSubmit() {
			return (this.mode === 'record' && this.audioData !== null)
				|| (this.mode === 'choose' && this.audioFilePath !== null)
		},
	},

	mounted() {
	},

	methods: {
		resetAudioState() {
			this.audioData = null
			this.audioFilePath = null
		},
		async onInputEnter() {
			if (this.mode === 'record') {
				const url = generateUrl('/apps/assistant/stt/transcribeAudio')
				const formData = new FormData()
				formData.append('audioData', this.audioData)
				await this.apiRequest(url, formData)
			} else {
				const url = generateUrl('/apps/assistant/stt/transcribeFile')
				const params = { path: this.audioFilePath }
				await this.apiRequest(url, params)
			}

			this.resetAudioState()
		},
		async apiRequest(url, data) {
			this.loading = true
			try {
				await axios.post(url, data)
				showSuccess(t('assistant', 'Successfully scheduled transcription'))
				this.$emit('submit', '')
			} catch (error) {
				console.error('API error:', error)
				this.resetAudioState()
				showError(
					t('assistant', 'Failed to schedule transcription')
					+ (': ' + error.response?.data
						|| error.message
						|| t('assistant', 'Unknown API error')))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.picker-content-wrapper {
	width: 100%;
}

.picker-content {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 12px 16px 16px 16px;

	h2 {
		display: flex;
		align-items: center;
		gap: 8px;

		.icon {
			color: var(--color-primary);
		}
	}

	.footer {
		display: flex;
		align-items: center;
		justify-content: end;
		gap: 8px;
		margin-top: 8px;
		width: 100%;
	}
}
</style>
