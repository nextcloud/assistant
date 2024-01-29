<template>
	<NcContent app-name="assistant">
		<NcAppContent>
			<div v-if="task?.id"
				class="assistant-wrapper">
				<RunningEmptyContent
					v-if="showSyncTaskRunning"
					:description="shortInput"
					@cancel="onCancelNSchedule" />
				<ScheduledEmptyContent
					v-else-if="showScheduleConfirmation"
					:description="shortInput"
					:show-close-button="false" />
				<AssistantTextProcessingForm
					v-else
					class="form"
					:inputs="task.inputs"
					:output="task.output ?? ''"
					:selected-task-type-id="task.taskType"
					:loading="loading"
					@submit="onSubmit"
					@sync-submit="onSyncSubmit" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

import AssistantTextProcessingForm from '../components/AssistantTextProcessingForm.vue'
import RunningEmptyContent from '../components/RunningEmptyContent.vue'
import ScheduledEmptyContent from '../components/ScheduledEmptyContent.vue'

import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { scheduleTask, runTask, cancelCurrentSyncTask } from '../assistant.js'

export default {
	name: 'TextProcessingTaskResultPage',

	components: {
		ScheduledEmptyContent,
		RunningEmptyContent,
		AssistantTextProcessingForm,
		NcContent,
		NcAppContent,
	},

	props: {
	},

	data() {
		return {
			task: loadState('assistant', 'task'),
			showSyncTaskRunning: false,
			showScheduleConfirmation: false,
			loading: false,
		}
	},

	computed: {
		shortInput() {
			let input = ''
			if (this.taskType === 'copywriter') {
				// Special case with a two part input:
				input = t('assistant', 'Writing style: ') + this.task.inputs.writingStyle + '; ' + t('assistant', 'Source material: ') + this.task.inputs.sourceMaterial
			} else {
				input = this.task.inputs.prompt
			}

			if (input.length <= 200) {
				return input
			}
			return input.slice(0, 200) + '…'
		},
	},

	mounted() {
	},

	methods: {
		onCancelNSchedule() {
			cancelCurrentSyncTask()
			scheduleTask(this.task.appId, this.task.identifier, this.task.taskType, this.task.inputs)
				.then((response) => {
					this.showSyncTaskRunning = false
					this.showScheduleConfirmation = true
					console.debug('scheduled task', response.data?.task)
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
					showError(t('assistant', 'Failed to schedule your task'))
				})
		},
		onSubmit(data) {
			scheduleTask(this.task.appId, this.task.identifier, data.textProcessingTaskTypeId, data.inputs)
				.then((response) => {
					this.task.inputs = data.inputs
					this.showScheduleConfirmation = true
					console.debug('scheduled task', response.data?.task)
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
					showError(t('assistant', 'Failed to schedule your task'))
				})
		},
		onSyncSubmit(data) {
			this.showSyncTaskRunning = true
			this.task.inputs = data.inputs
			this.task.taskType = data.textProcessingTaskTypeId
			runTask(this.task.appId, this.task.identifier, data.textProcessingTaskTypeId, data.inputs)
				.then((response) => {
					this.task.output = response.data?.task?.output ?? ''
					this.showSyncTaskRunning = false
					console.debug('Assistant SYNC result', response.data)
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.assistant-wrapper {
	display: flex;
	justify-content: center;
	margin: 24px 16px 16px 16px;
	.form {
		width: 600px;
	}
}
</style>
