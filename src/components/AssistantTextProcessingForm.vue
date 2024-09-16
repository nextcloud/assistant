<template>
	<NcLoadingIcon v-if="loadingTaskTypes" />
	<NoProviderEmptyContent v-else-if="taskTypes.length === 0" />
	<div v-else
		class="assistant-form">
		<span class="assistant-bubble">
			<CreationIcon :size="16" class="icon" />
			<span>{{ t('assistant', 'Nextcloud Assistant') }}</span>
		</span>
		<TaskTypeSelect
			:value.sync="mySelectedTaskTypeId"
			class="task-custom-select"
			:options="sortedTaskTypes"
			@update:value="onTaskTypeUserChange" />
		<div v-if="showHistory"
			class="history">
			<div class="history--title">
				<NcButton
					class="history--back-button"
					type="secondary"
					:title="t('assistant', 'Back to the assistant')"
					@click="showHistory = false">
					<template #icon>
						<NcLoadingIcon v-if="historyLoading" />
						<ArrowLeftIcon v-else />
					</template>
				</NcButton>
				<h3 v-if="selectedTaskType">
					{{ t('assistant', 'Previous "{taskTypeName}" tasks', { taskTypeName: selectedTaskType.name }) }}
				</h3>
			</div>
			<TaskList
				class="history--list"
				:task-type="selectedTaskType"
				:loading.sync="historyLoading"
				@try-again="onHistoryTryAgain"
				@load-task="onHistoryLoadTask" />
		</div>
		<div v-else class="task-input-output-form">
			<AssistantFormInputs v-if="selectedTaskType"
				:inputs.sync="myInputs"
				:selected-task-id="selectedTaskId"
				:selected-task-type="selectedTaskType"
				:show-advanced.sync="showAdvanced" />
			<AssistantFormOutputs v-if="hasOutput"
				:inputs="myInputs"
				:outputs.sync="myOutputs"
				:selected-task-type="selectedTaskType"
				:show-advanced.sync="showAdvanced" />
		</div>
		<!-- hide the footer for chatty-llm -->
		<div v-if="!showHistory && mySelectedTaskTypeId !== 'chatty-llm'" class="footer">
			<NcButton v-if="selectedTaskType"
				class="history-button"
				type="secondary"
				:aria-label="t('assistant', 'Show previous tasks')"
				@click="showHistory = true">
				<template #icon>
					<HistoryIcon />
				</template>
				{{ t('assistant', 'Previous "{taskTypeName}" tasks', { taskTypeName: selectedTaskType.name }) }}
			</NcButton>
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
					type="primary"
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
					:type="b.type ?? 'secondary'"
					:title="b.title"
					@click="onActionButtonClick(b)">
					{{ b.label }}
					<template v-if="b.iconSvg" #icon>
						<NcIconSvgWrapper :svg="b.iconSvg" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import CreationIcon from 'vue-material-design-icons/Creation.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import UnfoldLessHorizontalIcon from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import UnfoldMoreHorizontalIcon from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import AssistantFormInputs from './AssistantFormInputs.vue'
import AssistantFormOutputs from './AssistantFormOutputs.vue'
import NoProviderEmptyContent from './NoProviderEmptyContent.vue'
import TaskList from './TaskList.vue'
import TaskTypeSelect from './TaskTypeSelect.vue'

import { SHAPE_TYPE_NAMES } from '../constants.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import Vue from 'vue'
import VueClipboard from 'vue-clipboard2'

Vue.use(VueClipboard)

const TEXT2TEXT_TASK_TYPE_ID = 'core:text2text'

export default {
	name: 'AssistantTextProcessingForm',
	components: {
		NoProviderEmptyContent,
		TaskList,
		TaskTypeSelect,
		NcButton,
		NcLoadingIcon,
		NcIconSvgWrapper,
		NcActions,
		NcActionButton,
		CreationIcon,
		UnfoldLessHorizontalIcon,
		UnfoldMoreHorizontalIcon,
		HistoryIcon,
		ArrowLeftIcon,
		AssistantFormInputs,
		AssistantFormOutputs,
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
		actionButtons: {
			type: Array,
			default: () => [],
		},
	},
	emits: [
		'sync-submit',
		'action-button-clicked',
		'try-again',
		'load-task',
	],
	data() {
		return {
			myInputs: this.inputs,
			myOutputs: this.outputs,
			taskTypes: [],
			mySelectedTaskTypeId: this.selectedTaskTypeId || TEXT2TEXT_TASK_TYPE_ID,
			showHistory: false,
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
			return this.taskTypes.slice().sort((a, b) => {
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
			if (taskType) {
				return (taskType.optionalInputShape && Object.keys(taskType.optionalInputShape).length > 0)
					|| (taskType.optionalOutputShape && Object.keys(taskType.optionalOutputShape.length) > 0)
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
				return ([SHAPE_TYPE_NAMES.Text, SHAPE_TYPE_NAMES.Enum].includes(fieldType) && typeof value === 'string' && !!value?.trim())
					|| ([
						SHAPE_TYPE_NAMES.Number,
						SHAPE_TYPE_NAMES.File,
						SHAPE_TYPE_NAMES.Image,
						SHAPE_TYPE_NAMES.Audio,
						SHAPE_TYPE_NAMES.Video,
					].includes(fieldType) && typeof value === 'number')
					|| (fieldType === SHAPE_TYPE_NAMES.ListOfTexts && typeof value === 'object' && !!value && value.every(v => typeof v === 'string'))
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
		getTaskTypes() {
			this.loadingTaskTypes = true
			axios.get(generateOcsUrl('/apps/assistant/api/v1/task-types'))
				.then((response) => {
					this.taskTypes = response.data.ocs.data.types
					// check if selected task type is in the list, fallback to text2text
					const taskType = this.taskTypes.find(tt => tt.id === this.mySelectedTaskTypeId)
					if (taskType === undefined) {
						const text2textType = this.taskTypes.find(tt => tt.id === TEXT2TEXT_TASK_TYPE_ID)
						if (text2textType) {
							this.mySelectedTaskTypeId = TEXT2TEXT_TASK_TYPE_ID
						} else {
							this.mySelectedTaskTypeId = null
						}
					}
					// add placeholders
					this.taskTypes.forEach(tt => {
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
						}
					})
				})
				.catch((error) => {
					console.error(error)
				})
				.then(() => {
					this.loadingTaskTypes = false
				})
		},
		onTaskTypeUserChange() {
			this.myOutputs = null
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
			this.showHistory = false
			this.$emit('try-again', e)
		},
		onHistoryLoadTask(e) {
			this.showHistory = false
			this.$emit('load-task', e)
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
	}

	.assistant-bubble {
		align-self: start;
		display: flex;
		gap: 8px;
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius-rounded);
		padding: 2px 8px;
		.icon {
			color: var(--color-primary);
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
			overflow: auto;
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
</style>
