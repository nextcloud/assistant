<template>
	<NcContent app-name="assistant">
		<NcAppContent>
			<div v-if="task.output">
				<AssistantPlainTextModal
					:output="task.output"
					:task-category="task.category" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

import AssistantPlainTextModal from '../components/AssistantPlainTextModal.vue'

import { showError } from '@nextcloud/dialogs'

export default {
	name: 'PlainTextResultPage',

	components: {
		NcContent,
		NcAppContent,
		AssistantPlainTextModal,
	},

	props: {
		task: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	mounted() {
		if (this.task?.output === undefined) {
			showError(t('assistant', 'The task could not be found. It may have been deleted.'))
		}
	},

	methods: {

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
