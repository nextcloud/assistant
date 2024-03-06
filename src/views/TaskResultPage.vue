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
					:output="task.output"
					:selected-task-type-id="task.taskType"
					:loading="loading"
					@submit="onSubmit"
					@sync-submit="onSyncSubmit"
					@try-again="onTryAgain"
					@load-task="onLoadTask" />
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
import {
	scheduleTask,
	runOrScheduleTask,
	scheduleTtiTask,
	runOrScheduleTtiTask,
	runSttTask,
	cancelCurrentSyncTask,
} from '../assistant.js'
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
			const scheduleFunction = this.task.taskType === 'OCP\\TextToImage\\Task'
				? scheduleTtiTask
				: scheduleTask
			scheduleFunction(this.task.appId, this.task.identifier, this.task.taskType, this.task.inputs)
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
		syncSubmit(inputs, taskTypeId, newTaskIdentifier = '') {
			this.showSyncTaskRunning = true
			this.task.inputs = inputs
			this.task.taskType = taskTypeId
			if (taskTypeId === 'speech-to-text') {
				runSttTask(inputs).then(response => {
					this.showScheduleConfirmation = true
					this.showSyncTaskRunning = false
				})
				return
			}
			const runOrScheduleFunction = taskTypeId === 'OCP\\TextToImage\\Task'
				? runOrScheduleTtiTask
				: runOrScheduleTask
			runOrScheduleFunction(this.task.appId, this.task.identifier, taskTypeId, inputs)
				.then((response) => {
					console.debug('Assistant SYNC result', response.data?.ocs?.data)
					const task = response.data?.ocs?.data?.task
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
		onSyncSubmit(data) {
			this.syncSubmit(data.inputs, data.selectedTaskTypeId, this.task.identifier)
		},
		onTryAgain(task) {
			this.syncSubmit(task.inputs, task.taskType)
		},
		onLoadTask(task) {
			if (this.loading === false) {
				this.task.taskType = task.taskType
				this.task.inputs = task.inputs
				this.task.status = task.status
				this.task.output = task.status === STATUS.successfull ? task.output : null
			}
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
