<template>
	<NcContent app-name="assistant">
		<NcAppContent>
			<div v-if="state?.result">
				<AssistantPlainTextModal
					:output="state.result"
					:task-type="state.taskType" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

import AssistantPlainTextModal from '../components/AssistantPlainTextModal.vue'

import { loadState } from '@nextcloud/initial-state'

import { showError } from '@nextcloud/dialogs'

export default {
	name: 'PlainTextResultPage',

	components: {
		NcContent,
		NcAppContent,
		AssistantPlainTextModal,
	},

	props: {
	},

	data() {
		return {
			state: loadState('assistant', 'plain-text-result'),
		}
	},

	computed: {
	},

	mounted() {
		if (this.state?.status !== 'success') {
			showError(t('assistant', 'The transcription could not be found. It may have been deleted.'))
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
