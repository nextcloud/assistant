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
			<NcCheckboxRadioSwitch v-if="state.assistant_available"
				:checked="state.assistant_enabled"
				@update:checked="onCheckboxChanged($event, 'assistant_enabled')">
				<div class="checkbox-text">
					{{ t('assistant', 'Enable Nextcloud Assistant in header') }}
				</div>
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="state.free_prompt_picker_available"
				:checked="state.free_prompt_picker_enabled"
				@update:checked="onCheckboxChanged($event, 'free_prompt_picker_enabled')">
				<div class="checkbox-text">
					{{ t('assistant', 'Enable AI text generation in smart picker') }}
				</div>
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="state.text_to_image_picker_available"
				:checked="state.text_to_image_picker_enabled"
				@update:checked="onCheckboxChanged($event, 'text_to_image_picker_enabled')">
				<div class="checkbox-text">
					{{ t('assistant', 'Enable AI image generation in smart picker') }}
				</div>
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="state.speech_to_text_picker_available"
				:checked="state.speech_to_text_picker_enabled"
				@update:checked="onCheckboxChanged($event, 'speech_to_text_picker_enabled')">
				<div class="checkbox-text">
					{{ t('assistant', 'Enable AI transcription in smart picker') }}
				</div>
			</NcCheckboxRadioSwitch>
			<div v-if="noProvidersAvailable" class="settings-hint">
				<InformationOutlineIcon class="icon" />
				<span>
					{{ t('assistant', 'No suitable providers are available. They must first be enabled by your administrator.') }}
				</span>
			</div>
			<div v-else>
				<h3>{{ t('assistant', 'Configured backends') }}</h3>
				<p>{{ t('assistant', 'The following services are used as backends for Nextcloud Assistant:') }}</p>
				<template v-for="(taskNames, providerName) in providers">
					<h5 :key="providerName">
						{{ providerName }}
					</h5>
					{{ taskNames.join(', ') }}
				</template>
			</div>
		</div>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		AssistantIcon,
		NcCheckboxRadioSwitch,
		InformationOutlineIcon,
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
