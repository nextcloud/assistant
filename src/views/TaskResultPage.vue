<template>
	<NcContent app-name="assistant">
		<NcAppContent>
			<div v-if="task?.id"
				class="assistant-wrapper">
				<RunningEmptyContent
					v-if="showSyncTaskRunning"
					@cancel="onCancelNSchedule" />
				<ScheduledEmptyContent
					v-else-if="showScheduleConfirmation"
					:description="shortInput"
					:show-close-button="false" />
				<AssistantForm
					v-else
					class="form"
					:input="task.input"
					:output="task.output ?? ''"
					:selected-task-type-id="task.type"
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

import AssistantForm from '../components/AssistantForm.vue'
import RunningEmptyContent from '../components/RunningEmptyContent.vue'
import ScheduledEmptyContent from '../components/ScheduledEmptyContent.vue'

import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { scheduleTask, runTask, cancelCurrentSyncTask } from '../assistant.js'

export default {
	name: 'TaskResultPage',

	components: {
		ScheduledEmptyContent,
		RunningEmptyContent,
		AssistantForm,
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
			if (this.task.input.length <= 200) {
				return this.task.input
			}
			return this.task.input.slice(0, 200) + '…'
		},
	},

	mounted() {
	},

	methods: {
		onCancelNSchedule() {
			cancelCurrentSyncTask()
			scheduleTask(this.task.appId, this.task.identifier, this.task.type, this.task.input)
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
			scheduleTask(this.task.appId, this.task.identifier, data.taskTypeId, data.input)
				.then((response) => {
					this.task.input = data.input
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
			this.task.input = data.input
			this.task.type = data.taskTypeId
			runTask(this.task.appId, this.task.identifier, data.taskTypeId, data.input)
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
