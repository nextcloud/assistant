<template>
	<NcModal v-if="show"
		:size="modalSize"
		:can-close="false"
		class="assistant-modal"
		@close="onCancel">
		<div ref="modal_content"
			class="assistant-modal--wrapper">
			<NcButton :aria-label="closeButtonLabel"
				:title="closeButtonTitle"
				type="tertiary"
				class="close-button"
				@click="onCancel">
				<template #icon>
					<CloseIcon />
				</template>
			</NcButton>
			<AssistantPlainTextResult
				class="assistant-modal--content"
				:output="output"
				:task-category="taskCategory" />
		</div>
	</NcModal>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import AssistantPlainTextResult from './AssistantPlainTextResult.vue'

export default {
	name: 'AssistantPlainTextModal',
	components: {
		AssistantPlainTextResult,
		NcModal,
		NcButton,
		CloseIcon,
	},
	props: {
		output: {
			type: String,
			default: '',
		},
		taskCategory: {
			type: [Number, null],
			default: null,
		},
	},
	emits: [
		'cancel',
	],
	data() {
		return {
			show: true,
			closeButtonTitle: t('assistant', 'Close'),
			closeButtonLabel: t('assistant', 'Close Nextcloud Assistant'),
			modalSize: 'normal',
		}
	},
	computed: {
	},
	mounted() {
	},
	methods: {
		onCancel() {
			this.show = false
			this.$emit('cancel')
		},
	},
}
</script>

<style lang="scss">
// this is to avoid scroll on the container and leave it to the result block
.assistant-modal .modal-container {
	display: flex !important;

	//&__content {
	//padding: 16px;
	//}
}
</style>

<style lang="scss" scoped>
.assistant-modal--wrapper {
	//width: 100%;
	padding: 16px;
	overflow-y: auto;

	.close-button {
		position: absolute;
		top: 4px;
		right: 4px;
		// No border on hover
		&:hover {
			outline: none;
		}
	}
}

.assistant-modal--content {
	width: 100%;

}
</style>
