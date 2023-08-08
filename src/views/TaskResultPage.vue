<template>
	<NcContent app-name="assistant">
		<NcAppContent>
			<div v-if="task?.id"
				class="assistant-wrapper">
				<NcEmptyContent
					v-if="showScheduleConfirmation"
					:title="t('assistant', 'Your task has been scheduled, you will receive a notification when it has finished')"
					:name="t('assistant', 'Your task has been scheduled, you will receive a notification when it has finished')"
					:description="shortInput">
					<template #icon>
						<AssistantIcon />
					</template>
				</NcEmptyContent>
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
import AssistantIcon from '../components/icons/AssistantIcon.vue'

import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import AssistantForm from '../components/AssistantForm.vue'

import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { scheduleTask, runTask } from '../assistant.js'

export default {
	name: 'TaskResultPage',

	components: {
		AssistantIcon,
		AssistantForm,
		NcContent,
		NcAppContent,
		NcEmptyContent,
	},

	props: {
	},

	data() {
		return {
			task: loadState('assistant', 'task'),
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
			this.loading = true
			runTask(this.task.appId, this.task.identifier, data.taskTypeId, data.input)
				.then((response) => {
					this.task.output = response.data
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
				})
				.then(() => {
					this.loading = false
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
		width: 550px;
	}
}
</style>
