<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcContent app-name="assistant">
		<NcAppContent>
			<div class="assistant-wrapper">
				<AssistantTextProcessingForm
					class="form"
					:selected-task-id="task.id"
					:inputs="task.input"
					:outputs="task.output"
					:selected-task-type-id="task.type"
					:loading="loading"
					:show-sync-task-running="showSyncTaskRunning"
					:short-input="shortInput"
					:progress="progress"
					:expected-runtime="expectedRuntime"
					:is-notify-enabled="isNotifyEnabled"
					@sync-submit="onSyncSubmit"
					@try-again="onTryAgain"
					@load-task="onLoadTask"
					@new-task="onNewTask"
					@background-notify="onBackgroundNotify"
					@cancel-task="onCancel" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

import AssistantTextProcessingForm from '../components/AssistantTextProcessingForm.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import {
	cancelTask,
	cancelTaskPolling,
	getTask,
	pollTask,
	scheduleTask,
	setNotifyReady,
} from '../assistant.js'
import { TASK_STATUS_STRING } from '../constants.js'

export default {
	name: 'AssistantPage',

	components: {
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
			loading: false,
			isNotifyEnabled: false,
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
		onBackgroundNotify(enable) {
			setNotifyReady(this.task.id, enable).then(res => {
				this.isNotifyEnabled = enable
			})
		},
		onCancel() {
			cancelTaskPolling()
			setNotifyReady(this.task.id, false)
			cancelTask(this.task.id).then(res => {
				this.loading = false
				this.showSyncTaskRunning = false
				this.task.id = null
			})
		},
		syncSubmit(inputs, taskTypeId, newTaskIdentifier = '') {
			this.showSyncTaskRunning = true
			this.isNotifyEnabled = false
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
						emit('assistant:task:updated', finishedTask)
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
			cancelTaskPolling()
			this.showSyncTaskRunning = false

			this.task.type = task.type
			this.task.input = task.input
			this.task.status = task.status
			this.task.output = task.status === TASK_STATUS_STRING.successful ? task.output : null
			this.task.id = task.id

			if ([TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(task?.status)) {
				getTask(task.id).then(response => {
					const updatedTask = response.data?.ocs?.data?.task

					if (![TASK_STATUS_STRING.scheduled, TASK_STATUS_STRING.running].includes(updatedTask?.status)) {
						this.selectedTaskTypeId = updatedTask.type
						this.task.input = updatedTask.input
						this.task.output = updatedTask.status === TASK_STATUS_STRING.successful ? updatedTask.output : null
						this.task.id = updatedTask.id
						return
					}

					this.loading = true
					this.showSyncTaskRunning = true
					this.progress = null
					this.task.completionExpectedAt = updatedTask.completionExpectedAt
					this.task.scheduledAt = updatedTask.scheduledAt

					const setProgress = (progress) => {
						this.progress = progress
					}
					pollTask(updatedTask.id, setProgress).then(finishedTask => {
						console.debug('pollTask.then', finishedTask)
						if (finishedTask.status === TASK_STATUS_STRING.successful) {
							this.task.output = finishedTask?.output
							this.task.id = finishedTask?.id
						} else if (finishedTask.status === TASK_STATUS_STRING.failed) {
							showError(t('assistant', 'Your task with ID {id} has failed', { id: finishedTask.id }))
							console.error('Assistant task failed', finishedTask)
							this.task.output = null
						}
						// resolve(finishedTask)
						this.loading = false
						this.showSyncTaskRunning = false
						emit('assistant:task:updated', finishedTask)
					}).catch(error => {
						console.debug('Assistant poll error', error)
					})
				}).catch(error => {
					console.error(error)
				})
			}
		},
		onNewTask() {
			cancelTaskPolling()
			this.loading = false
			this.showSyncTaskRunning = false
			this.isNotifyEnabled = false
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
