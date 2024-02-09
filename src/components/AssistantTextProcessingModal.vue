<template>
	<NcModal v-if="show"
		:size="modalSize"
		:can-close="false"
		class="assistant-modal"
		@close="onCancel">
		<div ref="modal_content"
			class="assistant-modal--wrapper">
			<div class="assistant-modal--content">
				<NcButton :aria-label="closeButtonLabel"
					:title="closeButtonTitle"
					type="tertiary"
					class="close-button"
					@click="onCancel">
					<template #icon>
						<CloseIcon />
					</template>
				</NcButton>
				<RunningEmptyContent
					v-if="showSyncTaskRunning"
					:description="shortInput"
					@cancel="onCancelNSchedule" />
				<ScheduledEmptyContent
					v-else-if="showScheduleConfirmation"
					:description="shortInput"
					:show-close-button="true"
					@close="onCancel" />
				<AssistantTextProcessingForm
					v-else
					class="form"
					:inputs="inputs"
					:output="output"
					:selected-task-type-id="selectedTaskTypeId"
					:loading="loading"
					:action-buttons="actionButtons"
					@submit="onSubmit"
					@sync-submit="onSyncSubmit"
					@action-button-clicked="onActionButtonClicked" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import AssistantTextProcessingForm from './AssistantTextProcessingForm.vue'
import RunningEmptyContent from './RunningEmptyContent.vue'
import ScheduledEmptyContent from './ScheduledEmptyContent.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'AssistantTextProcessingModal',
	components: {
		ScheduledEmptyContent,
		RunningEmptyContent,
		AssistantTextProcessingForm,
		NcModal,
		NcButton,
		CloseIcon,
	},
	props: {
		/**
		 * If true, add the modal content to the Viewer trap elements via the event-bus
		 */
		isInsideViewer: {
			type: Boolean,
			default: false,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		inputs: {
			type: Object,
			default: () => {},
		},
		output: {
			type: [String, null],
			default: null,
		},
		selectedTaskTypeId: {
			type: [String, null],
			default: null,
		},
		showSyncTaskRunning: {
			type: Boolean,
			default: false,
		},
		showScheduleConfirmation: {
			type: Boolean,
			required: true,
		},
		actionButtons: {
			type: Array,
			default: () => [],
		},
	},
	emits: [
		'cancel',
		'submit',
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
		// TODO: Fix this to support multiple inputs
		shortInput() {
			const input = this.inputs[0] ?? ''
			if (input.length <= 200) {
				return input
			}
			return input.slice(0, 200) + '…'
		},
	},
	mounted() {
		if (this.isInsideViewer) {
			const elem = this.$refs.modal_content
			emit('viewer:trapElements:changed', elem)
		}
	},
	methods: {
		onCancel() {
			this.show = false
			this.$emit('cancel')
		},
		onSubmit(params) {
			// this.show = false
			this.$emit('submit', params)
		},
		onSyncSubmit(params) {
			this.$emit('sync-submit', params)
		},
		onCancelNSchedule() {
			this.$emit('cancel-sync-n-schedule')
		},
		onActionButtonClicked(data) {
			this.$emit('action-button-clicked', data)
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
.close-button {
	position: absolute;
	top: 4px;
	right: 4px;
	z-index: 1;
	background-color: var(--color-main-background);
}

.assistant-modal--wrapper {
	//width: 100%;
	padding: 16px;
	overflow-y: auto;
}

.assistant-modal--content {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	overflow-y: auto;

	> h2 {
		display: flex;
		margin: 12px 0 20px 0;
		.icon {
			margin-right: 8px;
		}
	}

	.form {
		width: 100%;
	}
}
</style>
