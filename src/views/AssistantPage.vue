<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcContent app-name="assistant">
		<NcAppContent>
			<div class="assistant-wrapper">
				<RunningEmptyContent
					v-if="showSyncTaskRunning"
					:description="shortInput"
					:progress="progress"
					:expected-runtime="expectedRuntime"
					@background-notify="onBackgroundNotify"
					@cancel="onCancel"
					@back="onBackToAssistant" />
				<ScheduledEmptyContent
					v-else-if="showScheduleConfirmation"
					:description="shortInput"
					:show-close-button="false"
					@back="onBackToAssistant" />
				<AssistantTextProcessingForm
					v-else
					class="form"
					:selected-task-id="task.id"
					:inputs="task.input"
					:outputs="task.output"
					:selected-task-type-id="task.type"
					:loading="loading"
					@sync-submit="onSyncSubmit"
					@try-again="onTryAgain"
					@load-task="onLoadTask"
					@new-task="onNewTask" />
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
	cancelTaskPolling,
	cancelTask,
	setNotifyReady,
	pollTask,
} from '../assistant.js'
import { TASK_STATUS_STRING } from '../constants.js'

export default {
	name: 'AssistantPage',

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
			progress: null,
			showScheduleConfirmation: false,
			loading: false,
		}
	},

	computed: {
		shortInput() {
			const input = this.task.input.input ?? this.task.input.sourceMaterial ?? ''
			if (typeof input === 'string') {
				if (input.length <= 200) {
					return input
				}
				return input.slice(0, 200) + '…'
			}
			return ''
		},
		expectedRuntime() {
			const expected = this.task.completionExpectedAt
			const scheduled = this.task.scheduledAt
			return (expected && scheduled) ? (expected - scheduled) : null
		},
	},

	mounted() {
		console.debug('[assistant] task', this.task)
	},

	methods: {
		onBackgroundNotify() {
			cancelTaskPolling()
			this.showScheduleConfirmation = true
			this.showSyncTaskRunning = false
			setNotifyReady(this.task.id)
		},
		onBackToAssistant() {
			this.showSyncTaskRunning = false
			this.showScheduleConfirmation = false
			this.task.output = null
		},
		onCancel() {
			cancelTaskPolling()
			cancelTask(this.task.id)
			this.showSyncTaskRunning = false
		},
		syncSubmit(inputs, taskTypeId, newTaskIdentifier = '') {
			this.showSyncTaskRunning = true
			this.progress = null
			this.task.completionExpectedAt = null
			this.task.scheduledAt = null
			this.task.input = inputs
			this.task.type = taskTypeId
			scheduleTask('assistant', this.task.identifier, taskTypeId, inputs)
				.then((response) => {
					console.debug('Assistant SYNC result', response.data?.ocs?.data)
					const task = response.data?.ocs?.data?.task
					this.task.id = task.id
					this.task.completionExpectedAt = task.completionExpectedAt
					this.task.scheduledAt = task.scheduledAt
					pollTask(task.id, this.setProgress).then(finishedTask => {
						if (finishedTask.status === TASK_STATUS_STRING.successful) {
							this.task.output = finishedTask?.output
						} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
							showError(t('assistant', 'Your task with ID {id} has failed', { id: finishedTask.id }))
							console.error('[assistant] Task failed', finishedTask)
							this.task.output = null
						}
						this.loading = false
						this.showSyncTaskRunning = false
					}).catch(error => {
						console.debug('[assistant] poll error', error)
					})
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
					showError(t('assistant', 'Failed to schedule your task'))
				})
				.then(() => {
				})
		},
		setProgress(progress) {
			this.progress = progress
		},
		onSyncSubmit(data) {
			this.syncSubmit(data.inputs, data.selectedTaskTypeId, this.task.identifier)
		},
		onTryAgain(task) {
			this.syncSubmit(task.input, task.type)
		},
		onLoadTask(task) {
			if (this.loading === false) {
				this.task.type = task.type
				this.task.input = task.input
				this.task.status = task.status
				this.task.output = task.status === TASK_STATUS_STRING.successful ? task.output : null
				this.task.id = task.id
			}
		},
		onNewTask() {
			this.task.status = TASK_STATUS_STRING.unknown
			this.task.output = null
			this.task.id = null
		},
	},
}
</script>

<style scoped lang="scss">
.assistant-wrapper {
	height: 90%;
	display: flex;
	justify-content: center;
	margin: 24px 16px 16px 16px;
	.form {
		width: 100%;
		//max-width: 1200px;
	}
}
</style>
