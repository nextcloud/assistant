<template>
	<div id="assistant_prefs" class="section">
		<h2>
			<AssistantIcon class="icon" />
			{{ t('assistant', 'Nextcloud Assistant') }}
		</h2>
		<div id="assistant-content">
			<NcCheckboxRadioSwitch
				:checked="state.assistant_enabled"
				@update:checked="onCheckboxChanged($event, 'assistant_enabled')">
				{{ t('assistant', 'Top-right assistant') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		AssistantIcon,
		NcCheckboxRadioSwitch,
	},

	props: [],

	data() {
		return {
			state: loadState('assistant', 'config'),
		}
	},

	computed: {
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
						+ ': ' + error.response?.request?.responseText
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
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		margin-top: 12px;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
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
