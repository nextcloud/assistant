<template>
	<div id="textprocessing_assistant_prefs" class="section">
		<h2>
			<AssistantIcon class="icon" />
			{{ t('textprocessing_assistant', 'Nextcloud assistant') }}
		</h2>
		<div id="assistant-content">
			<div>
				<h3>
					{{ t('textprocessing_assistant', 'Select which features you want to enable') }}
				</h3>
				<NcCheckboxRadioSwitch
					:checked="state.assistant_enabled"
					@update:checked="onCheckboxChanged($event, 'assistant_enabled')">
					{{ t('textprocessing_assistant', 'Top-right assistant') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import HelpCircleIcon from 'vue-material-design-icons/HelpCircle.vue'

import AssistantIcon from './icons/AssistantIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import {   loadState  } from '@nextcloud/initial-state'
import {  generateUrl  } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		AssistantIcon,
		NcCheckboxRadioSwitch,
	},

	props: [],

	data() {
		return {
			state: loadState('textprocessing_assistant', 'admin-config'),
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
			const url = generateUrl('/apps/textprocessing_assistant/admin-config')
			return axios.put(url, req)
				.then((response) => {
					showSuccess(t('textprocessing_assistant', 'Assistant admin options saved'))
				})
				.catch((error) => {
					showError(
						t('textprocessing_assistant', 'Failed to save assistant admin options')
						+ ': ' + error.response?.request?.responseText
					)
				})
		},
	},
}
</script>

<style scoped lang="scss">
#textprocessing_assistant_prefs {
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
