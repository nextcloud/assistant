<template>
	<div id="assistant_prefs" class="section">
		<h2>
			<AssistantIcon class="icon" />
			{{ t('assistant', 'Nextcloud Assistant') }}
		</h2>
		<p><a href="https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html">{{ t('assistant', 'Find more details on how to set up Assistant and recommended backends in the Administration documentation.') }}</a></p>
		<div id="assistant-content">
			<div>
				<h3>
					{{ t('assistant', 'Select which features you want to enable') }}
				</h3>
				<NcCheckboxRadioSwitch
					:checked="state.assistant_enabled"
					@update:checked="onCheckboxChanged($event, 'assistant_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Top-right assistant') }}
						<div v-if="!state.text_processing_available" class="checkbox-text">
							<InformationOutlineIcon class="icon" />
							<span>
								{{ t('assistant', 'To be able to use this feature, please install at least one AI text processing provider.') }}
							</span>
						</div>
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="state.free_prompt_picker_enabled"
					:disabled="!state.free_prompt_task_type_available"
					@update:checked="onCheckboxChanged($event, 'free_prompt_picker_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'AI text generation smart picker') }}
						<div v-if="!state.free_prompt_task_type_available" class="checkbox-text">
							<InformationOutlineIcon class="icon" />
							<span>
								{{ t('assistant', 'To enable this feature, please install an AI text processing provider for the free prompt task type:') }}
							</span>
							<ul>
								<li><a href="https://github.com/nextcloud/llm2#readme">Local Large language model app</a></li>
								<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
							</ul>
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
								{{ t('assistant', 'To enable this feature, please install a text-to-image provider:') }}
							</span>
							<ul>
								<li><a href="https://github.com/nextcloud/text2image_stablediffusion#readme">Local Text-To-Image StableDiffusion</a></li>
								<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
							</ul>
						</div>
					</div>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:checked="state.speech_to_text_picker_enabled"
					:disabled="!state.speech_to_text_picker_available"
					@update:checked="onCheckboxChanged($event, 'speech_to_text_picker_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Speech-to-text smart picker') }}
						<div v-if="!state.speech_to_text_picker_available" class="checkbox-text">
							<InformationOutlineIcon class="icon" />
							<span>
								{{ t('assistant', 'To enable this feature, please install a speech-to-text provider:') }}
							</span>
							<ul>
								<li><a href="https://github.com/nextcloud/stt_whisper2#readme">Local Speech-To-Text Whisper</a></li>
								<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
							</ul>
						</div>
					</div>
				</NcCheckboxRadioSwitch>
			</div>
			<div class="chat-with-ai">
				<h2>
					{{ t('assistant', 'Chat with AI') }}
				</h2>
				<div class="line">
					<label for="chat_user_instructions">
						{{ t('assistant', 'Chat User Instructions for Chat Completions') }}
					</label>
				</div>
				<NcNoteCard type="info">
					<p>{{ t('assistant', 'It is passed on to the LLM for it to better understand the context.') }}</p>
					<p>{{ t('assistant', '"{user}" is a placeholder for the user\'s display name.') }}</p>
				</NcNoteCard>
				<NcRichContenteditable id="chat_user_instructions"
					class="text-field"
					:value.sync="state.chat_user_instructions"
					:auto-complete="() => {}"
					:link-auto-complete="false"
					:placeholder="t('assistant', 'Chat User Instructions for Chat Completions')"
					:aria-label="t('assistant', 'Chat User Instructions for Chat Completions')"
					dir="auto"
					@update:value="delayedValueUpdate(state.chat_user_instructions, 'chat_user_instructions')"
					@submit="delayedValueUpdate(state.chat_user_instructions, 'chat_user_instructions')" />
				<div class="line">
					<label for="chat_user_instructions_title">
						{{ t('assistant', 'Chat User Instructions for Title Generation') }}
					</label>
				</div>
				<NcNoteCard type="info">
					<p>{{ t('assistant', 'It is passed on to the LLMs to let it know what to do') }}</p>
					<p>{{ t('assistant', '"{user}" is a placeholder for the user\'s display name here as well.') }}</p>
				</NcNoteCard>
				<NcRichContenteditable id="chat_user_instructions_title"
					class="text-field"
					:value.sync="state.chat_user_instructions_title"
					:auto-complete="() => {}"
					:link-auto-complete="false"
					:placeholder="t('assistant', 'Chat User Instructions for Title Generation')"
					:aria-label="t('assistant', 'Chat User Instructions for Title Generation')"
					dir="auto"
					@update:value="delayedValueUpdate(state.chat_user_instructions_title, 'chat_user_instructions_title')"
					@submit="delayedValueUpdate(state.chat_user_instructions_title, 'chat_user_instructions_title')" />
				<div class="line">
					<label for="chat_last_n_messages">
						{{ t('assistant', 'Last N messages to consider for chat completions') }}
					</label>
				</div>
				<NcNoteCard type="info">
					<p>{{ t('assistant', ' This includes the user instructions and the LLM\'s messages') }}</p>
				</NcNoteCard>
				<NcTextField id="chat_last_n_messages"
					class="text-field"
					type="number"
					:value.sync="state.chat_last_n_messages"
					:error="!isUnsignedIntStr(state.chat_last_n_messages)"
					:title="t('assistant', 'Number of messages to consider for chat completions (excluding the user instructions, which is always considered)')"
					@update:value="delayedValueUpdate(state.chat_last_n_messages, 'chat_last_n_messages')" />
			</div>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import AssistantIcon from './icons/AssistantIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

import { delay } from '../utils.js'

export default {
	name: 'AdminSettings',

	components: {
		AssistantIcon,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcRichContenteditable,
		NcTextField,
		InformationOutlineIcon,
	},

	data() {
		return {
			state: loadState('assistant', 'admin-config'),
			textFieldSaveTimer: null,
			inputErrorTimer: null,
			optionsToSave: {},
		}
	},

	computed: {
	},

	methods: {
		isUnsignedIntStr(value) {
			return /^\d+$/.test(value)
		},
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		delayedValueUpdate(newValue, key) {
			delay(() => {
				this.optionsToSave[key] = newValue
				this.saveOptions(this.optionsToSave)
			}, 2000)
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/assistant/admin-config')
			return axios.put(url, req)
				.then(() => {
					showSuccess(t('assistant', 'Assistant admin options saved'))
				})
				.catch((error) => {
					console.error('Failed to save assistant admin options', error)
					showError(
						t('assistant', 'Failed to save assistant admin options')
						+ ': ' + (
							error.response?.data?.message
							?? error.response?.request?.responseText
						),
					)
				})
		},
	},
}
</script>

<style scoped lang="scss">
#assistant_prefs {
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

	.chat-with-ai {
		display: flex;
		flex-direction: column;

		> .line > label {
			width: 900px !important;
			font-weight: bold;
		}

		.notecard,
		.text-field {
			max-width: 900px;
		}
	}
}
</style>
