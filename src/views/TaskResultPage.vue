<template>
	<NcContent app-name="textprocessing_assistant">
		<NcAppContent>
			<div v-if="task?.id"
				class="assistant-wrapper">
				<NcEmptyContent
					v-if="showScheduleConfirmation"
					:title="t('textprocessing_assistant', 'Your task has been scheduled')"
					:name="t('textprocessing_assistant', 'Your task has been scheduled')"
					:description="t('textprocessing_assistant', 'You will receive a notification when it has finished')">
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
					@submit="onSubmit" />
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
import { scheduleTask } from '../assistant.js'

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
			task: loadState('textprocessing_assistant', 'task'),
			showScheduleConfirmation: false,
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
		onSubmit(data) {
			scheduleTask(this.task.appId, this.task.identifier, data.taskTypeId, data.input)
				.then((response) => {
					this.showScheduleConfirmation = true
					console.debug('scheduled task', response.data?.ocs?.data?.task)
				})
				.catch(error => {
					console.error('Assistant scheduling error', error)
					showError(t('textprocessing_assistant', 'Failed to schedule your task'))
				})
		},
	},
}
</script>

<style scoped lang="scss">
.assistant-wrapper {
	display: flex;
	justify-content: center;
	margin: 24px 0 16px 0;
	.form {
		width: 400px;
	}
}
</style>
