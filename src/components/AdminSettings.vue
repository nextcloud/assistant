<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
					:model-value="state.assistant_enabled"
					@update:model-value="onCheckboxChanged($event, 'assistant_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Enable Nextcloud Assistant in header') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcNoteCard v-if="!state.text_processing_available" type="info">
					{{ t('assistant', 'To be able to use this feature, please install at least one AI task processing provider.') }}
				</NcNoteCard>
				<NcCheckboxRadioSwitch
					:model-value="state.free_prompt_picker_enabled"
					:disabled="!state.free_prompt_task_type_available"
					@update:model-value="onCheckboxChanged($event, 'free_prompt_picker_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Enable AI text generation in smart picker') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcNoteCard v-if="!state.free_prompt_task_type_available" type="info">
					<div class="checkbox-text">
						<span>
							{{ t('assistant', 'To enable this feature, please install an AI task processing provider for the free prompt task type:') }}
						</span>
						<ul>
							<li><a href="https://github.com/nextcloud/llm2#readme">Local Large language model app</a></li>
							<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
						</ul>
					</div>
				</NcNoteCard>
				<NcCheckboxRadioSwitch
					:model-value="state.text_to_image_picker_enabled"
					:disabled="!state.text_to_image_picker_available"
					@update:model-value="onCheckboxChanged($event, 'text_to_image_picker_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Enable text-to-image in smart picker') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcNoteCard v-if="!state.text_to_image_picker_available" type="info">
					<div class="checkbox-text">
						<span>
							{{ t('assistant', 'To enable this feature, please install a text-to-image provider:') }}
						</span>
						<ul>
							<li><a href="https://github.com/nextcloud/text2image_stablediffusion#readme">Local Text-To-Image StableDiffusion</a></li>
							<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
						</ul>
					</div>
				</NcNoteCard>
				<NcCheckboxRadioSwitch
					:model-value="state.speech_to_text_picker_enabled"
					:disabled="!state.speech_to_text_picker_available"
					@update:model-value="onCheckboxChanged($event, 'speech_to_text_picker_enabled')">
					<div class="checkbox-text">
						{{ t('assistant', 'Enable speech-to-text in smart picker') }}
					</div>
				</NcCheckboxRadioSwitch>
				<NcNoteCard v-if="!state.speech_to_text_picker_available" type="info">
					<div class="checkbox-text">
						<span>
							{{ t('assistant', 'To enable this feature, please install a speech-to-text provider:') }}
						</span>
						<ul>
							<li><a href="https://github.com/nextcloud/stt_whisper2#readme">Local Speech-To-Text Whisper</a></li>
							<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
						</ul>
					</div>
				</NcNoteCard>
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
					v-model="state.chat_user_instructions"
					class="text-field"
					:auto-complete="() => {}"
					:link-auto-complete="false"
					:placeholder="t('assistant', 'Chat User Instructions for Chat Completions')"
					:aria-label="t('assistant', 'Chat User Instructions for Chat Completions')"
					dir="auto"
					@update:model-value="delayedValueUpdate(state.chat_user_instructions, 'chat_user_instructions')"
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
					v-model="state.chat_user_instructions_title"
					class="text-field"
					:auto-complete="() => {}"
					:link-auto-complete="false"
					:placeholder="t('assistant', 'Chat User Instructions for Title Generation')"
					:aria-label="t('assistant', 'Chat User Instructions for Title Generation')"
					dir="auto"
					@update:model-value="delayedValueUpdate(state.chat_user_instructions_title, 'chat_user_instructions_title')"
					@submit="delayedValueUpdate(state.chat_user_instructions_title, 'chat_user_instructions_title')" />
				<div class="line">
					<label for="chat_last_n_messages">
						{{ t('assistant', 'Last N messages to consider for chat completions') }}
					</label>
				</div>
				<NcNoteCard type="info">
					<p>{{ t('assistant', 'This includes the user instructions and the LLM\'s messages') }}</p>
				</NcNoteCard>
				<NcTextField id="chat_last_n_messages"
					v-model="state.chat_last_n_messages"
					type="number"
					class="text-field"
					:error="!isUnsignedIntStr(state.chat_last_n_messages)"
					:title="t('assistant', 'Number of messages to consider for chat completions (excluding the user instructions, which is always considered)')"
					@update:model-value="delayedValueUpdate(state.chat_last_n_messages, 'chat_last_n_messages')" />
			</div>
		</div>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcTextField from '@nextcloud/vue/components/NcTextField'

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
		flex-direction: column;
	}

	h2 {
		justify-content: start;
		display: flex;
		align-items: center;
		gap: 8px;
		margin-top: 8px;
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

	.notecard,
	.text-field {
		max-width: 900px;
	}

	.chat-with-ai {
		display: flex;
		flex-direction: column;

		> .line > label {
			width: 900px !important;
			font-weight: bold;
		}
	}
}
</style>
