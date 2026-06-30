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
				{{ t('assistant', 'Administration documentation') }}
				<OpenInNewIcon :size="16" />
			</a>
		</NcNoteCard>
		<NcNoteCard v-if="state.text_to_sticker_available && !state.text_to_image_picker_available" type="info">
			{{ t('assistant', 'The sticker generation feature won`t work without being able to generate images. Please install and enable a "Generate image" provider or disable the "Generate sticker" task type.') }}
		</NcNoteCard>
		<div id="assistant-content">
			<div>
				<NcFormGroup :label="t('assistant', 'Select which features you want to enable')"
					:hide-label="true"
					class="switch-group">
					<NcFormBox>
						<NcFormBoxSwitch :model-value="state.assistant_enabled"
							@update:model-value="onCheckboxChanged($event, 'assistant_enabled')">
							{{ t('assistant', 'Nextcloud Assistant in header') }}
						</NcFormBoxSwitch>
						<NcFormBoxSwitch :model-value="state.free_prompt_picker_enabled"
							:disabled="!state.free_prompt_task_type_available"
							@update:model-value="onCheckboxChanged($event, 'free_prompt_picker_enabled')">
							{{ t('assistant', 'AI text generation in the smart picker') }}
						</NcFormBoxSwitch>
						<NcFormBoxSwitch :model-value="state.text_to_image_picker_enabled"
							:disabled="!state.text_to_image_picker_available"
							@update:model-value="onCheckboxChanged($event, 'text_to_image_picker_enabled')">
							{{ t('assistant', 'Text-to-image in the smart picker') }}
						</NcFormBoxSwitch>
						<NcFormBoxSwitch :model-value="state.text_to_sticker_picker_enabled"
							:disabled="!state.text_to_image_picker_available"
							@update:model-value="onCheckboxChanged($event, 'text_to_sticker_picker_enabled')">
							{{ t('assistant', 'Text-to-sticker in the smart picker') }}
						</NcFormBoxSwitch>
						<NcFormBoxSwitch :model-value="state.speech_to_text_picker_enabled"
							:disabled="!state.speech_to_text_picker_available"
							@update:model-value="onCheckboxChanged($event, 'speech_to_text_picker_enabled')">
							{{ t('assistant', 'Speech-to-text in the smart picker') }}
						</NcFormBoxSwitch>
					</NcFormBox>
				</NcFormGroup>
				<NcNoteCard v-if="!state.task_processing_available" type="warning">
					{{ t('assistant', 'To be able to use the Assistant, please install at least one AI task processing provider.') }}
				</NcNoteCard>
				<NcNoteCard v-if="!state.free_prompt_task_type_available" type="info">
					<div class="checkbox-text">
						<span>
							{{ t('assistant', 'To enable text generation in the smart picker, please install an AI task processing provider for the "Free text to text prompt" task type:') }}
						</span>
						<ul>
							<li>
								<a href="https://github.com/nextcloud/llm2#readme" class="external link-with-icon" target="_blank">
									Local Large language model app
									<OpenInNewIcon :size="16" />
								</a>
							</li>
							<li>
								<a href="https://apps.nextcloud.com/apps/integration_openai" class="external link-with-icon" target="_blank">
									OpenAI/LocalAI Integration
									<OpenInNewIcon :size="16" />
								</a>
							</li>
						</ul>
					</div>
				</NcNoteCard>
				<NcNoteCard v-if="!state.text_to_image_picker_available" type="info">
					<div class="checkbox-text">
						<span>
							{{ t('assistant', 'To enable the sticker generation picker or the image generation picker, please install and enable a "Generate image" provider:') }}
						</span>
						<ul>
							<li>
								<a href="https://github.com/nextcloud/text2image_stablediffusion#readme" class="external link-with-icon" target="_blank">
									Local Text-To-Image StableDiffusion
									<OpenInNewIcon :size="16" />
								</a>
							</li>
							<li>
								<a href="https://apps.nextcloud.com/apps/integration_openai" class="external link-with-icon" target="_blank">
									OpenAI/LocalAI Integration
									<OpenInNewIcon :size="16" />
								</a>
							</li>
						</ul>
					</div>
				</NcNoteCard>
				<NcNoteCard v-if="!state.speech_to_text_picker_available" type="info">
					<div class="checkbox-text">
						<span>
							{{ t('assistant', 'To enable speech-to-text in the smart picker, please install and enable a "Transcribe audio" provider:') }}
						</span>
						<ul>
							<li>
								<a href="https://github.com/nextcloud/stt_whisper2#readme" class="external link-with-icon" target="_blank">
									Local Speech-To-Text Whisper
									<OpenInNewIcon :size="16" />
								</a>
							</li>
							<li>
								<a href="https://apps.nextcloud.com/apps/integration_openai" class="external link-with-icon" target="_blank">
									OpenAI/LocalAI Integration
									<OpenInNewIcon :size="16" />
								</a>
							</li>
						</ul>
					</div>
				</NcNoteCard>
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
					{{ t('assistant', 'It is passed on to the LLMs to better generate a chat title') }}
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
			<div v-if="state.context_agent_available" class="global-skills">
				<h4>
					{{ t('assistant', 'Global Agent Skills') }}
				</h4>
				<NcNoteCard type="info">
					{{ t('assistant', 'Select a folder containing skills (sub-folders, each holding a SKILL.md file). These will be available to all users in addition to their personal skills in "Chat with AI". The folder is resolved from the filesystem of the admin who set it.') }}
				</NcNoteCard>
				<div class="global-skills__row">
					<NcButton variant="secondary" @click="onPickGlobalSkillsFolder">
						<template #icon>
							<FolderPlusOutlineIcon />
						</template>
						{{ t('assistant', 'Select global skills folder') }}
					</NcButton>
					<NcButton v-if="state.global_skills_path"
						variant="tertiary"
						@click="onClearGlobalSkillsFolder">
						{{ t('assistant', 'Clear') }}
					</NcButton>
				</div>
				<div v-if="state.global_skills_path" class="global-skills__current">
					<NcTextField :model-value="state.global_skills_path"
						class="text-field"
						:label="t('assistant', 'Selected folder')"
						:readonly="true" />
					<span class="global-skills__set-by">
						{{ t('assistant', 'Set by') }}
					</span>
					<NcAvatar :user="state.global_skills_admin_uid"
						:hide-status="true" />
					<span>{{ state.global_skills_admin_uid }}</span>
				</div>
				<div v-else class="global-skills__current">
					<span>{{ t('assistant', 'No global skills folder configured') }}</span>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import FolderPlusOutlineIcon from 'vue-material-design-icons/FolderPlusOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import AssistantIcon from './icons/AssistantIcon.vue'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

import { delay } from '../utils.js'

export default {
	name: 'AdminSettings',

	components: {
		AssistantIcon,
		FolderPlusOutlineIcon,
		NcAvatar,
		NcButton,
		NcNoteCard,
		NcRichContenteditable,
		NcTextField,
		NcFormGroup,
		NcFormBox,
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
		async onPickGlobalSkillsFolder() {
			const picker = getFilePickerBuilder(t('assistant', 'Select global skills folder'))
				.setMultiSelect(false)
				.allowDirectories(true)
				.setMimeTypeFilter(['httpd/unix-directory'])
				.addButton({
					id: 'choose-global-skills-folder',
					label: t('assistant', 'Select'),
					variant: 'primary',
					callback: (nodes) => {
						if (!nodes || nodes.length === 0 || !nodes[0].path) {
							showError(t('assistant', 'No folder selected'))
							return
						}
						this.saveGlobalSkillsFolder(nodes[0].path)
					},
				})
				.build()
			await picker.pick()
		},
		onClearGlobalSkillsFolder() {
			this.saveGlobalSkillsFolder('')
		},
		saveGlobalSkillsFolder(path) {
			const url = generateUrl('/apps/assistant/admin-config/global-skills')
			return axios.put(url, { path })
				.then(() => {
					this.state.global_skills_path = path
					this.state.global_skills_admin_uid = path === '' ? '' : (getCurrentUser()?.uid ?? '')
					showSuccess(t('assistant', 'Global skills folder updated'))
				})
				.catch((error) => {
					console.error('Failed to set global skills folder', error)
					showError(
						t('assistant', 'Failed to set global skills folder')
						+ ': ' + (
							error.response?.data?.message
							?? error.response?.request?.responseText
						),
					)
				})
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
	max-width: 800px;
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		margin-top: 12px;
		.icon {
			margin-right: 4px;
		}
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

	.global-skills {
		display: flex;
		flex-direction: column;
		margin-top: 16px;

		&__row {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
			margin-top: 8px;
		}

		&__current {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
			margin-top: 8px;
		}

		&__set-by {
			color: var(--color-text-maxcontrast);
		}
	}
}
</style>
