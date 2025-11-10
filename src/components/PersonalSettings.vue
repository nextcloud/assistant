<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="assistant_prefs" class="section">
		<h2>
			<AssistantIcon />
			{{ t('assistant', 'Nextcloud Assistant') }}
		</h2>
		<div id="assistant-content">
			<NcFormBox class="switch-group">
				<NcFormBoxSwitch v-if="state.assistant_available"
					:model-value="state.assistant_enabled"
					@update:model-value="onCheckboxChanged($event, 'assistant_enabled')">
					{{ t('assistant', 'Enable Nextcloud Assistant in header') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch v-if="state.audio_chat_available"
					:model-value="state.autoplay_audio_chat"
					@update:model-value="onCheckboxChanged($event, 'autoplay_audio_chat')">
					{{ t('assistant', 'Auto-play audio chat responses') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch v-if="state.free_prompt_picker_available"
					:model-value="state.free_prompt_picker_enabled"
					@update:model-value="onCheckboxChanged($event, 'free_prompt_picker_enabled')">
					{{ t('assistant', 'Enable AI text generation in smart picker') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch v-if="state.text_to_image_picker_available"
					:model-value="state.text_to_image_picker_enabled"
					@update:model-value="onCheckboxChanged($event, 'text_to_image_picker_enabled')">
					{{ t('assistant', 'Enable AI image generation in smart picker') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch v-if="state.text_to_sticker_picker_available"
					:model-value="state.text_to_sticker_picker_enabled"
					@update:model-value="onCheckboxChanged($event, 'text_to_sticker_picker_enabled')">
					{{ t('assistant', 'Enable AI sticker generation in smart picker') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch v-if="state.speech_to_text_picker_available"
					:model-value="state.speech_to_text_picker_enabled"
					@update:model-value="onCheckboxChanged($event, 'speech_to_text_picker_enabled')">
					{{ t('assistant', 'Enable AI transcription in smart picker') }}
				</NcFormBoxSwitch>
			</NcFormBox>
			<NcNoteCard v-if="noProvidersAvailable" type="warning">
				{{ t('assistant', 'No suitable providers are available. They must first be enabled by your administrator.') }}
			</NcNoteCard>
			<div v-else>
				<h3>{{ t('assistant', 'Configured backends') }}</h3>
				<p>{{ t('assistant', 'The following services are used as backends for Nextcloud Assistant:') }}</p>
				<div v-for="(taskNames, providerName) in providers" :key="providerName">
					<h5>
						{{ providerName }}
					</h5>
					{{ taskNames.join(', ') }}
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'

import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		AssistantIcon,
		NcFormBox,
		NcFormBoxSwitch,
		NcNoteCard,
	},

	props: [],

	data() {
		return {
			state: loadState('assistant', 'config'),
			providers: loadState('assistant', 'availableProviders'),
		}
	},

	computed: {
		noProvidersAvailable() {
			return this.state.text_to_image_picker_available === false
				&& this.state.text_processing_available === false
				&& this.state.speech_to_text_picker_available === false
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/assistant/config')
			return axios.put(url, req)
				.then((response) => {
					showSuccess(t('assistant', 'Assistant options saved'))
				})
				.catch((error) => {
					showError(
						t('assistant', 'Failed to save assistant options')
						+ ': ' + error.response?.request?.responseText,
					)
				})
		},
	},
}
</script>

<style scoped lang="scss">
#assistant_prefs {
	#assistant-content {
		margin-left: 40px;
	}

	h2 {
		display: flex;
		align-items: center;
		justify-content: start;
		gap: 8px;
	}

	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		margin-top: 12px;
		.icon {
			margin-right: 4px;
		}
	}

	.switch-group {
		max-width: 500px;
	}

	.checkbox-text {
		display: flex;
		flex-direction: row;

		.icon {
			margin-right: 8px;
			margin-left: 24px;
		}
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 300px;
		}
	}
}
</style>
