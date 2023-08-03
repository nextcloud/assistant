<template>
	<NcContent app-name="textprocessing_assistant">
		<NcAppContent>
			<div v-if="task?.id"
				class="assistant-wrapper">
				<AssistantForm
					class="form"
					:input="task.input"
					:output="task.output"
					:selected-task-type-id="task.type" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

import AssistantForm from '../components/AssistantForm.vue'

import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'TaskResultPage',

	components: {
		AssistantForm,
		NcContent,
		NcAppContent,
	},

	props: {
	},

	data() {
		return {
			task: loadState('textprocessing_assistant', 'task'),
		}
	},

	computed: {
	},

	mounted() {
		console.debug('aaaaa MOUNTED', this.task)
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.assistant-wrapper {
	display: flex;
	justify-content: center;
	margin: 16px 0;
	.form {
		width: 400px;
	}
}
</style>
