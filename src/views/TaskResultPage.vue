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
import { scheduleTask, runSttTask, cancelCurrentSyncTask, runTtiTask, runOrScheduleTask } from '../assistant.js'
import { STATUS } from '../constants.js'

export default {
	name: 'TaskResultPage',

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
			const input = this.task.inputs.prompt ?? this.task.inputs.sourceMaterial ?? ''
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
			scheduleTask(this.task.appId, this.task.identifier, this.task.type, this.task.inputs)
				.then((response) => {
					this.showSyncTaskRunning = false
					this.showScheduleConfirmation = true
					console.debug('scheduled task', response.data?.ocs?.data?.task)
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
					showError(t('assistant', 'Failed to schedule your task'))
				})
		},
		onSubmit(data) {
			scheduleTask(this.task.appId, this.task.identifier, data.taskTypeId, data.inputs)
				.then((response) => {
					this.task.inputs = data.inputs
					this.showScheduleConfirmation = true
					console.debug('scheduled task', response.data?.ocs?.data?.task)
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
			if (data.textProcessingTaskTypeId === 'speech-to-text') {
				runSttTask(data.inputs).then(response => {
					this.showScheduleConfirmation = true
					this.showSyncTaskRunning = false
				})
				return
			}
			const runOrScheduleFunction = data.textProcessingTaskTypeId === 'OCP\\TextToImage\\Task'
				? runTtiTask
				: runOrScheduleTask
			runOrScheduleFunction(this.task.appId, this.task.identifier, data.textProcessingTaskTypeId, data.inputs)
				.then((response) => {
					console.debug('Assistant SYNC result', response.data)
					const task = response.data?.task
					this.task.inputs = task.inputs
					if (task.status === STATUS.successfull) {
						this.task.output = task?.output ?? ''
					} else if (task.status === STATUS.scheduled) {
						this.showScheduleConfirmation = true
					}
					this.loading = false
					this.showSyncTaskRunning = false
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
