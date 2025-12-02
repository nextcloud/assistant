<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcLoadingIcon v-if="loadingTaskTypes" />
	<NoProviderEmptyContent v-else-if="taskTypes.length === 0" />
	<div v-else
		class="assistant-form">
		<span class="assistant-bubble">
			<NcAssistantIcon />
			<strong class="assistant-bubble__label">{{ t('assistant', 'Nextcloud Assistant') }}</strong>
		</span>
		<TaskTypeSelect
			v-model="mySelectedTaskTypeId"
			class="task-custom-select"
			:options="sortedTaskTypes"
			@update:model-value="onTaskTypeUserChange" />
		<div class="task-input-output-form">
			<ChattyLLMInputForm v-if="mySelectedTaskTypeId === 'chatty-llm'" class="chatty-inputs" />
			<div v-else class="container chatty-inputs">
				<NcAppNavigation>
					<NcAppNavigationList>
						<NcAppNavigationNew :text="t('assistant', 'New task')"
							variant="secondary"
							@click="onHistoryNewTask">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
						</NcAppNavigationNew>
						<TaskList
							v-model:loading="historyLoading"
							:task-type="selectedTaskType"
							:selected-task-id="selectedTaskId"
							class="history--list"
							@try-again="onHistoryTryAgain"
							@load-task="onHistoryLoadTask"
							@task-deleted="onHistoryTaskDeleted" />
					</NcAppNavigationList>
				</NcAppNavigation>
				<RunningEmptyContent
					v-if="showSyncTaskRunning"
					class="running-area"
					:description="shortInput"
					:progress="progress"
					:expected-runtime="expectedRuntime"
					:is-notify-enabled="isNotifyEnabled"
					:task-status="taskStatus"
					:scheduled-at="scheduledAt"
					@background-notify="$emit('background-notify', $event)"
					@cancel="$emit('cancel-task')" />
				<NcAppContent v-else class="session-area">
					<div class="session-area__top-bar">
						<div class="session-area__top-bar__title">
							<EditableTextField :initial-text="selectedTaskType.name ?? ''" />
						</div>
					</div>
					<div v-if="mySelectedTaskTypeId === 'core:text2text:translate'"
						class="session-area__chat-area">
						<TranslateForm v-if="selectedTaskType"
							ref="translateForm"
							v-model:inputs="myInputs"
							v-model:outputs="myOutputs"
							v-model:show-advanced="showAdvanced"
							:translate-task-id="selectedTaskId"
							:translate-task-type="selectedTaskType" />
					</div>
					<div v-else class="session-area__chat-area">
						<AssistantFormInputs v-if="selectedTaskType"
							ref="assistantFormInputs"
							v-model:inputs="myInputs"
							v-model:show-advanced="showAdvanced"
							:selected-task-id="selectedTaskId"
							:selected-task-type="selectedTaskType"
							@submit="onSyncSubmit" />
						<AssistantFormOutputs v-if="hasOutput"
							v-model:show-advanced="showAdvanced"
							v-model:outputs="myOutputs"
							:inputs="myInputs"
							:selected-task-type="selectedTaskType" />
					</div>
					<div class="footer">
						<div class="footer--action-buttons">
							<NcActions v-if="hasOptionalInputOutputShape"
								:force-menu="true">
								<NcActionButton
									:close-after-click="true"
									@click="showAdvanced = !showAdvanced">
									<template #icon>
										<UnfoldLessHorizontalIcon v-if="showAdvanced" />
										<UnfoldMoreHorizontalIcon v-else />
									</template>
									{{ toggleAdvancedLabel }}
								</NcActionButton>
							</NcActions>
							<NcButton
								v-if="showSubmit"
								variant="primary"
								class="submit-button"
								:disabled="!canSubmit"
								:title="syncSubmitButtonTitle"
								@click="onSyncSubmit">
								{{ syncSubmitButtonLabel }}
								<template #icon>
									<NcLoadingIcon v-if="loading" />
									<CreationIcon v-else />
								</template>
							</NcButton>
							<NcButton
								v-for="(b, i) in actionButtonsToShow"
								:key="i"
								:variant="b.variant ?? b.type ?? 'secondary'"
								:title="b.title"
								@click="onActionButtonClick(b)">
								{{ b.label }}
								<template v-if="b.iconSvg" #icon>
									<NcIconSvgWrapper :svg="b.iconSvg" />
								</template>
							</NcButton>
						</div>
					</div>
				</NcAppContent>
			</div>
		</div>
	</div>
</template>

<script>
import CreationIcon from 'vue-material-design-icons/Creation.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import UnfoldLessHorizontalIcon from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import UnfoldMoreHorizontalIcon from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationList from '@nextcloud/vue/components/NcAppNavigationList'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAssistantIcon from '@nextcloud/vue/components/NcAssistantIcon'

import AssistantFormInputs from './AssistantFormInputs.vue'
import AssistantFormOutputs from './AssistantFormOutputs.vue'
import ChattyLLMInputForm from './ChattyLLM/ChattyLLMInputForm.vue'
import EditableTextField from './ChattyLLM/EditableTextField.vue'
import NoProviderEmptyContent from './NoProviderEmptyContent.vue'
import RunningEmptyContent from './RunningEmptyContent.vue'
import TaskList from './TaskList.vue'
import TaskTypeSelect from './TaskTypeSelect.vue'
import TranslateForm from './Translate/TranslateForm.vue'

import { SHAPE_TYPE_NAMES, MAX_TEXT_INPUT_LENGTH } from '../constants.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

import { saveLastSelectedTaskType } from '../assistant.js'

const TEXT2TEXT_TASK_TYPE_ID = 'core:text2text'
const CHAT_TASK_TYPE_ID = 'chatty-llm'

export default {
	name: 'AssistantTextProcessingForm',
	components: {
		NoProviderEmptyContent,
		RunningEmptyContent,
		TaskList,
		TaskTypeSelect,
		TranslateForm,
		NcButton,
		NcLoadingIcon,
		NcIconSvgWrapper,
		NcActions,
		NcActionButton,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationList,
		NcAppNavigationNew,
		NcAssistantIcon,
		CreationIcon,
		PlusIcon,
		UnfoldLessHorizontalIcon,
		UnfoldMoreHorizontalIcon,
		AssistantFormInputs,
		AssistantFormOutputs,
		ChattyLLMInputForm,
		EditableTextField,
	},
	provide() {
		return {
			providedCurrentTaskId: () => this.selectedTaskId,
		}
	},
	props: {
		loading: {
			type: Boolean,
			default: false,
		},
		selectedTaskId: {
			type: [Number, null],
			default: null,
		},
		inputs: {
			type: Object,
			default: () => {},
		},
		outputs: {
			type: [Object, null],
			default: null,
		},
		selectedTaskTypeId: {
			type: [String, null],
			default: null,
		},
		showSyncTaskRunning: {
			type: Boolean,
			default: false,
		},
		shortInput: {
			type: String,
			required: true,
		},
		progress: {
			type: [Number, null],
			default: null,
		},
		expectedRuntime: {
			type: [Number, null],
			default: null,
		},
		isNotifyEnabled: {
			type: Boolean,
			default: false,
		},
		actionButtons: {
			type: Array,
			default: () => [],
		},
		taskTypeIdList: {
			type: [Array, null],
			default: null,
		},
		taskStatus: {
			type: [String, null],
			default: null,
		},
		scheduledAt: {
			type: [Number, null],
			default: null,
		},
	},
	emits: [
		'sync-submit',
		'action-button-clicked',
		'try-again',
		'load-task',
		'new-task',
		'cancel-task',
		'background-notify',
	],
	data() {
		return {
			myInputs: this.inputs,
			myOutputs: this.outputs,
			taskTypes: [],
			mySelectedTaskTypeId: this.selectedTaskTypeId || CHAT_TASK_TYPE_ID,
			loadingTaskTypes: false,
			historyLoading: false,
			showAdvanced: false,
		}
	},
	computed: {
		selectedTaskType() {
			if (this.mySelectedTaskTypeId === null) {
				return null
			}
			const taskType = this.taskTypes.find(tt => tt.id === this.mySelectedTaskTypeId)
			if (taskType !== undefined && taskType) {
				return taskType
			}
			return null
		},
		sortedTaskTypes() {
			const filteredTaskTypes = this.taskTypeIdList !== null
				? this.taskTypes.slice().filter(t => this.taskTypeIdList.find(tt => tt === t.id))
				: this.taskTypes.slice()

			return filteredTaskTypes.sort((a, b) => {
				const prioA = a.priority
				const prioB = b.priority
				return prioA > prioB
					? 1
					: prioA < prioB
						? -1
						: 0
			})
		},
		hasOptionalInputOutputShape() {
			const taskType = this.selectedTaskType
			console.debug('[assistant] selected taskType', taskType)
			console.debug('[assistant] selected taskType', Object.keys(taskType.optionalInputShape).length)
			console.debug('[assistant] selected taskType', Object.keys(taskType.optionalOutputShape).length)
			if (taskType) {
				return (taskType.optionalInputShape && Object.keys(taskType.optionalInputShape).length > 0)
					|| (taskType.optionalOutputShape && Object.keys(taskType.optionalOutputShape).length > 0)
			}
			return false
		},
		toggleAdvancedLabel() {
			return this.showAdvanced
				? t('assistant', 'Hide advanced options')
				: t('assistant', 'Show advanced options')
		},
		showSubmit() {
			return this.selectedTaskType
		},
		canSubmit() {
			// otherwise, check that none of the properties of myInputs are empty
			console.debug('[assistant] canSubmit', this.myInputs)
			if (Object.keys(this.myInputs).length === 0) {
				return false
			}
			const taskType = this.selectedTaskType
			// check that all fields required by the task type are defined
			return Object.keys(taskType.inputShape).every(k => {
				if (this.myInputs[k] === null || this.myInputs[k] === undefined) {
					return false
				}
				const fieldType = taskType.inputShape[k].type
				const value = this.myInputs[k]
				return ([SHAPE_TYPE_NAMES.Text, SHAPE_TYPE_NAMES.Enum].includes(fieldType)
						&& typeof value === 'string'
						&& !!value?.trim()
						// check that the input text is not too long for text fields
						&& (fieldType === SHAPE_TYPE_NAMES.Enum || value.trim().length <= MAX_TEXT_INPUT_LENGTH))
					|| ([
						SHAPE_TYPE_NAMES.Number,
						SHAPE_TYPE_NAMES.File,
						SHAPE_TYPE_NAMES.Image,
						SHAPE_TYPE_NAMES.Audio,
						SHAPE_TYPE_NAMES.Video,
					].includes(fieldType) && typeof value === 'number')
					|| (fieldType === SHAPE_TYPE_NAMES.ListOfTexts && typeof value === 'object' && !!value && value.every(v => {
						return typeof v === 'string'
							&& !!v?.trim()
							&& v.trim().length <= MAX_TEXT_INPUT_LENGTH
					}))
					|| (fieldType === SHAPE_TYPE_NAMES.ListOfNumbers && typeof value === 'object' && !!value && value.every(v => typeof v === 'number'))
					|| ([
						SHAPE_TYPE_NAMES.ListOfFiles,
						SHAPE_TYPE_NAMES.ListOfImages,
						SHAPE_TYPE_NAMES.ListOfAudios,
						SHAPE_TYPE_NAMES.ListOfVideos,
					].includes(fieldType) && typeof value === 'object' && !!value && value.every(v => typeof v === 'number'))
			})
		},
		syncSubmitButtonLabel() {
			return this.hasOutput
				? t('assistant', 'Try again')
				: this.selectedTaskType.id === TEXT2TEXT_TASK_TYPE_ID
					? t('assistant', 'Send request')
					: this.selectedTaskType.name
		},
		syncSubmitButtonTitle() {
			return this.hasOutput
				? t('assistant', 'Launch this task again')
				: t('assistant', 'Launch a task')
		},
		hasOutput() {
			return this.myOutputs
				&& Object.keys(this.myOutputs).length > 0
				&& Object.values(this.myOutputs).every(v => v !== null)
		},
		formattedOutput() {
			if (this.mySelectedTaskTypeId === 'OCP\\TextToImage\\Task') {
				return window.location.protocol + '//' + window.location.host + generateUrl('/apps/assistant/i/{imageGenId}', { imageGenId: this.myOutput })
			}
			return this.myOutput.trim()
		},
		actionButtonsToShow() {
			return this.hasOutput ? this.actionButtons : []
		},
	},
	watch: {
		outputs(newVal) {
			console.debug('update output in proc form', newVal)
			this.myOutputs = newVal
		},
		inputs(newVal) {
			this.myInputs = newVal
		},
		mySelectedTaskTypeId(newVal) {
			this.myOutputs = {}
		},
	},
	mounted() {
		this.getTaskTypes()
		console.debug('[assistant] form\'s myoutputs', this.myOutputs)
	},
	methods: {
		// Parse the file if a fileId is passed as initial value to a text field
		parseTextFileInputs(taskType) {
			if (taskType === undefined || taskType === null) {
				return
			}
			Object.keys(this.myInputs).forEach(k => {
				if (taskType.inputShape[k]?.type === 'Text') {
					if (this.myInputs[k]?.fileId || this.myInputs[k]?.filePath) {
						const { filePath, fileId } = { fileId: this.myInputs[k]?.fileId, filePath: this.myInputs[k]?.filePath }
						this.myInputs[k] = ''
						this.parseFile({ fileId, filePath })
							.then(response => {
								if (response.data?.ocs?.data?.parsedText) {
									this.myInputs[k] = response.data?.ocs?.data?.parsedText
								}
							})
							.catch(error => {
								console.error(error)
								showError(t('assistant', 'Failed to parse some files'))
							})
					}
				}
			})
		},
		parseFile({ filePath, fileId }) {
			const url = generateOcsUrl('/apps/assistant/api/v1/parse-file')
			return axios.post(url, {
				filePath,
				fileId,
			})
		},
		getTaskTypes() {
			this.loadingTaskTypes = true
			axios.get(generateOcsUrl('/apps/assistant/api/v1/task-types'))
				.then((response) => {
					const taskTypes = response.data.ocs.data.types
					// check if selected task type is in the list, fallback to text2text
					const taskType = taskTypes.find(tt => tt.id === this.mySelectedTaskTypeId)
					if (taskType === undefined) {
						const text2textType = taskTypes.find(tt => tt.id === TEXT2TEXT_TASK_TYPE_ID)
						const chatType = taskTypes.find(tt => tt.id === CHAT_TASK_TYPE_ID)
						if (chatType) {
							this.mySelectedTaskTypeId = CHAT_TASK_TYPE_ID
						} else if (text2textType) {
							this.parseTextFileInputs(text2textType)
							this.mySelectedTaskTypeId = TEXT2TEXT_TASK_TYPE_ID
						} else if (taskTypes.length > 0) {
							this.mySelectedTaskTypeId = taskTypes[0]?.id
						} else {
							this.mySelectedTaskTypeId = null
						}
					} else {
						this.parseTextFileInputs(taskType)
					}

					// add placeholders
					taskTypes.forEach(tt => {
						if (tt.id === TEXT2TEXT_TASK_TYPE_ID && tt.inputShape.input) {
							tt.inputShape.input.placeholder = t('assistant', 'Generate a first draft for a blog post about privacy')
						} else if (tt.id === 'context_chat:context_chat' && tt.inputShape.prompt) {
							tt.inputShape.prompt.placeholder = t('assistant', 'What is the venue for the team retreat this quarter?')
						} else if (tt.id === 'core:text2text:summary' && tt.inputShape.input) {
							tt.inputShape.input.placeholder = t('assistant', 'Type or paste the text to summarize')
						} else if (tt.id === 'core:text2text:headline' && tt.inputShape.input) {
							tt.inputShape.input.placeholder = t('assistant', 'Type or paste the text to generate a headline for')
						} else if (tt.id === 'core:text2text:topics' && tt.inputShape.input) {
							tt.inputShape.input.placeholder = t('assistant', 'Type or paste the text to extract the topics from')
						} else if (tt.id === 'core:text2image' && tt.inputShape.input && tt.inputShape.numberOfImages) {
							tt.inputShape.input.placeholder = t('assistant', 'landscape trees forest peaceful')
							tt.inputShape.numberOfImages.placeholder = t('assistant', 'a number')
						} else if (tt.id === 'core:contextwrite' && tt.inputShape.source_input && tt.inputShape.style_input) {
							tt.inputShape.style_input.placeholder = t('assistant', 'Shakespeare or an example of the style')
							tt.inputShape.source_input.placeholder = t('assistant', 'A description of what you need or some original content')
						} else if (tt.id === 'core:text2text:translate') {
							if (!tt.inputShapeDefaults.origin_language) {
								tt.inputShapeDefaults.origin_language = tt.inputShapeEnumValues.origin_language[0].value
							}
							const defaultTargetLanguage = OCA.Assistant.last_target_language
							if (defaultTargetLanguage) {
								tt.inputShapeDefaults.target_language = defaultTargetLanguage
							}
						}
					})
					this.taskTypes = taskTypes
				})
				.catch((error) => {
					console.error(error)
				})
				.then(() => {
					this.loadingTaskTypes = false
				})
		},
		onTaskTypeUserChange() {
			this.$emit('new-task')

			const lastInput = this.myInputs?.input
				|| this.myInputs?.source_input // used in context write
				|| this.myInputs?.text // used in document generation

			if (typeof lastInput === 'string' || lastInput instanceof String) {
				OCA.Assistant.last_text_input = lastInput
			}

			this.$refs?.translateForm?.setDefaultValues(true)
			this.$refs?.assistantFormInputs?.setDefaultValues(true)

			// keep last prompt if it's a string and if the new task type has text input
			if (OCA.Assistant.last_text_input) {
				if (this.selectedTaskType?.inputShape?.input && this.selectedTaskType?.inputShape?.input.type === SHAPE_TYPE_NAMES.Text) {
					this.myInputs.input = OCA.Assistant.last_text_input
				}
				if (this.selectedTaskType?.inputShape?.source_input && this.selectedTaskType?.inputShape?.source_input.type === SHAPE_TYPE_NAMES.Text) {
					this.myInputs.source_input = OCA.Assistant.last_text_input
				}
				if (this.selectedTaskType?.inputShape?.text && this.selectedTaskType?.inputShape?.text.type === SHAPE_TYPE_NAMES.Text) {
					this.myInputs.text = OCA.Assistant.last_text_input
				}
			}
			saveLastSelectedTaskType(this.mySelectedTaskTypeId)
		},
		onSyncSubmit() {
			console.debug('[assistant] in form submit ---------', this.myInputs)
			this.$emit('sync-submit', { inputs: this.myInputs, selectedTaskTypeId: this.mySelectedTaskTypeId })
		},
		onActionButtonClick(button) {
			console.debug('[assistant] action button clicked', button, this.myOutputs)
			this.$emit('action-button-clicked', { button, output: this.myOutputs })
		},
		onHistoryTryAgain(e) {
			this.$emit('try-again', e)
		},
		onHistoryLoadTask(e) {
			this.$emit('load-task', e)
		},
		onHistoryNewTask() {
			this.$emit('new-task')

			this.$refs?.translateForm?.setDefaultValues(true)
			this.$refs?.assistantFormInputs?.setDefaultValues(true)
		},
		onHistoryTaskDeleted(task) {
			// if the currently selected task is deleted, simulate a click on "new task"
			// this will stop the task status polling and display the form again
			if (task.id === this.selectedTaskId) {
				this.onHistoryNewTask()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.assistant-form {
	//width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: start;
	gap: 12px;
	overflow-y: auto;
	overflow-x: hidden;

	h2 {
		margin-top: 0;
	}

	.task-input-output-form {
		display: flex;
		flex-direction: column;
		width: 100%;
		// to make it max height, it will overflow anyway
		height: 100000px;
		overflow: auto;

		> * {
			margin-right: 6px;
		}

		.chatty-inputs {
			margin-top: 8px;
			height: 8000px;
		}
	}

	.assistant-bubble {
		align-self: center;
		display: flex;
		align-items: center;
		padding: 2px 8px;
		&__label {
			// this is only effective if --color-element-assistant-icon is not set (in NC < 32)
			background: var(--color-main-text);
			background-image: var(--color-element-assistant-icon);
			color: transparent;
			background-clip: text;
		}
	}

	.task-custom-select {
		width: 100%;
	}

	.task-action-select {
		width: 100%;
	}

	.task-name {
		margin-bottom: 0px;
	}

	.task-name,
	.task-description {
		align-self: start;
	}

	.footer {
		width: 100%;
		display: flex;
		&--action-buttons {
			flex-grow: 1;
			display: flex;
			flex-wrap: wrap;
			justify-content: end;
			gap: 4px;
		}
	}

	.history {
		width: 100%;
		// to make it max height, it will overflow anyway
		height: 100000px;
		display: flex;
		flex-direction: column;
		align-items: end;
		overflow: auto;

		&--list {
			width: 100%;
		}

		&--title {
			width: 100%;
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 4px;
			h3 {
				margin-top: 0px;
				margin-bottom: 0px;
			}
		}
	}

	.success-icon {
		color: var(--color-success);
	}
}

.container {
	overflow: auto;
	display: flex;
	height: 100%;

	:deep(.app-navigation-new) {
		padding: 0;
	}

	.unloaded-sessions {
		display: flex;
		justify-content: center;
		align-items: center;
		gap: 1em;
		font-weight: bold;
		padding: 1em;
		height: 100%;
	}

	:deep(.app-navigation) {
		--app-navigation-max-width: calc(100vw - (var(--app-navigation-padding) + 24px + var(--default-grid-baseline)));
		background-color: var(--color-primary-element-light);
		color: var(--color-primary-element-light-text);
		border-radius: var(--border-radius-large);

		@media only screen and (max-width: 1024px) {
			position: relative !important;
		}

		.app-navigation-toggle-wrapper {
			margin-right: -49px !important;
			top: var(--default-grid-baseline);
		}
	}

	:deep(.app-navigation--close) {
		.app-navigation-toggle-wrapper {
			margin-right: -33px !important;
		}
	}

	:deep(.app-navigation--close ~ .session-area) {
		.session-area__chat-area, .session-area__input-area {
			padding-left: 0 !important;
		}
		.session-area__top-bar {
			padding-left: 36px !important;
		}
	}

	:deep(.app-navigation-list) {
		padding: var(--default-grid-baseline) !important;
		box-sizing: border-box;
		height: 100%;

		.app-navigation-input-confirm > form {
			align-items: center;
			height: var(--default-clickable-area);

			> button {
				scale: calc(36/44);
			}
		}

		.app-navigation-entry-wrapper .app-navigation-entry-link {
			.app-navigation-entry-icon {
				display: none;
			}
			.app-navigation-entry__name {
				margin-left: 16px;
			}
		}

		.app-navigation-entry {
			&-link {
				padding-right: 0.3em;
			}

			&.active {
				font-weight: bold;

				&:hover {
					background-color: var(--color-primary-element) !important;
				}
			}

			&:hover {
				background-color: var(--color-primary-element-light-hover);
			}

			.app-navigation-entry-button {
				border: none !important;
				padding-right: 0 !important;

				> span {
					font-size: 100% !important;
					padding-left: 0;
				}
			}

			.editingContainer {
				margin: 0 !important;
				width: 100% !important;
				padding-left: 24px;
			}
		}
	}

	.session-area {
		display: flex;
		flex-direction: column;
		justify-content: space-between;

		&__top-bar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 4px;
			position: sticky;
			top: 0;
			height: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 2);
			box-sizing: border-box;
			border-bottom: 1px solid var(--color-border);
			padding-left: 52px;
			padding-right: 0.5em;
			font-weight: bold;
			background-color: var(--color-main-background);

			&__title {
				width: 100%;
			}
		}

		&__chat-area {
			flex: 1;
			display: flex;
			flex-direction: column;
			overflow-y: auto;
			padding: 1em;

			&__active-session__utility-button {
				display: flex;
				justify-content: center;
				padding: 1em;
			}
		}

		&__chat-area, &__input-area {
			padding-left: 1em;
		}

		&__agency-confirmation {
			margin-left: 1em;
		}

		&__input-area {
			position: sticky;
			bottom: 0;
		}
	}

	.running-area {
		width: 100%;
		padding: 16px;
	}
}
</style>
