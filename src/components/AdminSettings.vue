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
		<NcNoteCard type="info">
			{{ t('assistant', 'Find more details on how to set up Assistant and recommended backends in the Administration documentation.') }}
			<br>
			<a href="https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html" class="external link-with-icon" target="_blank">
				{{ t('assistant', 'Open Administration documentation') }}
				<OpenInNewIcon :size="16" />
			</a>
		</NcNoteCard>
		<NcNoteCard v-if="state.text_to_sticker_available && !state.text_to_image_picker_available" type="warning">
			{{ t('assistant', 'The sticker generation feature won`t work without being able to generate images. Please install and enable a "Generate image" provider or disable the "Generate sticker" task type.') }}
		</NcNoteCard>
		<div id="assistant-content">
			<div>
				<NcFormGroup :label="t('assistant', 'Select which features you want to enable')"
					class="switch-group">
					<NcFormBoxSwitch :model-value="state.assistant_enabled"
						@update:model-value="onCheckboxChanged($event, 'assistant_enabled')">
						{{ t('assistant', 'Enable Nextcloud Assistant in header') }}
					</NcFormBoxSwitch>
					<NcNoteCard v-if="!state.text_processing_available" type="warning">
						{{ t('assistant', 'To be able to use this feature, please install at least one AI task processing provider.') }}
					</NcNoteCard>
					<NcFormBoxSwitch :model-value="state.free_prompt_picker_enabled"
						:disabled="!state.free_prompt_task_type_available"
						@update:model-value="onCheckboxChanged($event, 'free_prompt_picker_enabled')">
						{{ t('assistant', 'Enable AI text generation in smart picker') }}
					</NcFormBoxSwitch>
					<NcNoteCard v-if="!state.free_prompt_task_type_available" type="info">
						<div class="checkbox-text">
							<span>
								{{ t('assistant', 'To enable text generation in the smart picker, please install an AI task processing provider for the "Free text to text prompt" task type:') }}
							</span>
							<ul>
								<li><a href="https://github.com/nextcloud/llm2#readme">Local Large language model app</a></li>
								<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
							</ul>
						</div>
					</NcNoteCard>
					<NcFormBoxSwitch :model-value="state.text_to_image_picker_enabled"
						:disabled="!state.text_to_image_picker_available"
						@update:model-value="onCheckboxChanged($event, 'text_to_image_picker_enabled')">
						{{ t('assistant', 'Enable text-to-image in smart picker') }}
					</NcFormBoxSwitch>
					<NcFormBoxSwitch :model-value="state.text_to_sticker_picker_enabled"
						:disabled="!state.text_to_image_picker_available"
						@update:model-value="onCheckboxChanged($event, 'text_to_sticker_picker_enabled')">
						{{ t('assistant', 'Enable text-to-sticker in smart picker') }}
					</NcFormBoxSwitch>
					<NcNoteCard v-if="!state.text_to_image_picker_available" type="warning">
						<div class="checkbox-text">
							<span>
								{{ t('assistant', 'To enable the sticker generation picker or the image generation picker, please install and enable a "Generate image" provider:') }}
							</span>
							<ul>
								<li><a href="https://github.com/nextcloud/text2image_stablediffusion#readme">Local Text-To-Image StableDiffusion</a></li>
								<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
							</ul>
						</div>
					</NcNoteCard>
					<NcFormBoxSwitch :model-value="state.speech_to_text_picker_enabled"
						:disabled="!state.speech_to_text_picker_available"
						@update:model-value="onCheckboxChanged($event, 'speech_to_text_picker_enabled')">
						{{ t('assistant', 'Enable speech-to-text in smart picker') }}
					</NcFormBoxSwitch>
					<NcNoteCard v-if="!state.speech_to_text_picker_available" type="warning">
						<div class="checkbox-text">
							<span>
								{{ t('assistant', 'To enable speech-to-text in the smart picker, please install and enable a "Transcribe audio" provider:') }}
							</span>
							<ul>
								<li><a href="https://github.com/nextcloud/stt_whisper2#readme">Local Speech-To-Text Whisper</a></li>
								<li><a href="https://apps.nextcloud.com/apps/integration_openai">OpenAI/LocalAI Integration</a></li>
							</ul>
						</div>
					</NcNoteCard>
				</NcFormGroup>
			</div>
			<div class="chat-with-ai">
				<h4>
					{{ t('assistant', 'Chat with AI') }}
				</h4>
				<div class="line">
					<label for="chat_user_instructions">
						{{ t('assistant', 'Chat User Instructions for Chat Completions') }}
					</label>
				</div>
				<NcNoteCard type="info">
					{{ t('assistant', 'It is passed on to the LLM for it to better understand the context.') }}
					<br>
					{{ t('assistant', '"{user}" is a placeholder for the user\'s display name.') }}
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
					{{ t('assistant', 'It is passed on to the LLMs to let it know what to do') }}
					<br>
					{{ t('assistant', '"{user}" is a placeholder for the user\'s display name here as well.') }}
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
					{{ t('assistant', 'This includes the user instructions and the LLM\'s messages') }}
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
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import AssistantIcon from './icons/AssistantIcon.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

import { delay } from '../utils.js'

export default {
	name: 'AdminSettings',

	components: {
		AssistantIcon,
		NcNoteCard,
		NcRichContenteditable,
		NcTextField,
		NcFormGroup,
		NcFormBoxSwitch,
		OpenInNewIcon,
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

	.switch-group {
		max-width: 500px;
	}

	.link-with-icon {
		display: flex;
		align-items: center;
		gap: 4px;
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
