<template>
	<div id="assistant_prefs" class="section">
		<h2>
			<AssistantIcon class="icon" />
			{{ t('assistant', 'Nextcloud Assistant') }}
		</h2>
		<div id="assistant-content">
			<div>
				<h3>
					{{ t('assistant', 'Select which features you want to enable') }}
				</h3>
				<NcCheckboxRadioSwitch
					:checked="state.assistant_enabled"
					:disabled="!state.text_processing_available"
					@update:checked="onCheckboxChanged($event, 'assistant_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Top-right assistant') }}
						<div v-if="!state.text_processing_available" class="checkbox-text">
							<InformationOutlineIcon class="icon" />
							<span>
								{{ t('assistant', 'To enable this feature, please install any AI text processing provider.') }}
							</span>
						</div>
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="state.free_prompt_picker_enabled"
					:disabled="!state.free_prompt_task_type_available"
					@update:checked="onCheckboxChanged($event, 'free_prompt_picker_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Free prompt smart picker') }}
						<div v-if="!state.free_prompt_task_type_available" class="checkbox-text">
							<InformationOutlineIcon class="icon" />
							<span>
								{{ t('assistant', 'To enable this feature, please install an AI text processing provider for the free prompt task type.') }}
							</span>
						</div>
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="state.text_to_image_picker_enabled"
					:disabled="!state.text_to_image_picker_available"
					@update:checked="onCheckboxChanged($event, 'text_to_image_picker_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Text-to-image smart picker') }}
						<div v-if="!state.text_to_image_picker_available" class="checkbox-text">
							<InformationOutlineIcon class="icon" />
							<span>
								{{ t('assistant', 'To enable this feature, please install a text-to-image provider.') }}
							</span>
						</div>
					</div>
				</NcCheckboxRadioSwitch>
			</div>
			<div>
				<h2>
					{{ t('assistant', 'Image storage') }}
				</h2>
				<div class="line">
					<label for="max_gen_idle_time">
						<CalendarClockIcon class="icon" />
						{{ t('assistant', 'Image generation idle time (days)') }}
					</label>
					<NcTextField id="max_image_gen_idle_time"
						class="text-field"
						:value.sync="imageGenerationIdleDays"
						:error="!isUnsignedIntStr(state.max_image_generation_idle_time)"
						:title="t('assistant', 'Days until generated images are deleted if they are not viewed')"
						@update:value="onUnsignedIntFieldChanged(state.max_image_generation_idle_time, 'max_image_generation_idle_time')" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'
import CalendarClockIcon from 'vue-material-design-icons/CalendarClock.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		AssistantIcon,
		NcCheckboxRadioSwitch,
		NcTextField,
		CalendarClockIcon,
		InformationOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('assistant', 'admin-config'),
			textFieldSaveTimer: null,
			inputErrorTimer: null,
			optionsToSave: {},
		}
	},

	computed: {
		imageGenerationIdleDays: {
			get() {
				if (this.isUnsignedIntStr(this.state.max_image_generation_idle_time)) {
					return (parseInt(this.state.max_image_generation_idle_time) / 60 / 60 / 24).toString()
				}
				return this.state.max_image_generation_idle_time
			},
			set(newValue) {
				if (this.isUnsignedIntStr(newValue)) {
					this.state.max_image_generation_idle_time = parseInt(newValue) * 60 * 60 * 24
				} else {
					this.state.max_image_generation_idle_time = newValue
				}
			},
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		isUnsignedIntStr(value) {
			return /^\d+$/.test(value)
		},
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onUnsignedIntFieldChanged(newValue, key) {
			if (this.isUnsignedIntStr(newValue)) {
				this.optionsToSave[key] = newValue

				if (this.textFieldSaveTimer) {
					clearTimeout(this.textFieldSaveTimer)
				}
				this.textFieldSaveTimer = setTimeout(() => {
					this.saveOptions(this.optionsToSave)
				}, 2000)
			}
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/assistant/admin-config')
			return axios.put(url, req)
				.then((response) => {
					showSuccess(t('assistant', 'Assistant admin options saved'))
				})
				.catch((error) => {
					showError(
						t('assistant', 'Failed to save assistant admin options')
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

	.checkbox-text {
		display: flex;
		flex-direction: row;

		.icon {
			margin-right: 8px;
			margin-left: 24px;
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
		.text-field {
			margin-left: 8px;
			width: 300px;
		}
	}
}
</style>
