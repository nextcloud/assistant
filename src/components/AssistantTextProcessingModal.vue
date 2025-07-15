<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal v-if="show"
		:size="modalSize"
		:no-close="true"
		:name="t('assistant', 'Nextcloud Assistant')"
		dark
		:container="null"
		class="assistant-modal"
		@close="onCancel">
		<div ref="modal_content"
			class="assistant-modal--wrapper">
			<div class="assistant-modal--content">
				<NcButton :aria-label="closeButtonLabel"
					:title="closeButtonTitle"
					variant="tertiary"
					class="close-button"
					@click="onCancel">
					<template #icon>
						<CloseIcon />
					</template>
				</NcButton>
				<AssistantTextProcessingForm
					class="form"
					:selected-task-id="selectedTaskId"
					:inputs="inputs"
					:outputs="outputs"
					:selected-task-type-id="selectedTaskTypeId"
					:loading="loading"
					:action-buttons="actionButtons"
					:show-sync-task-running="showSyncTaskRunning"
					:short-input="shortInput"
					:progress="progress"
					:expected-runtime="expectedRuntime"
					:is-notify-enabled="isNotifyEnabled"
					:task-type-id-list="taskTypeIdList"
					@sync-submit="onSyncSubmit"
					@action-button-clicked="onActionButtonClicked"
					@try-again="onTryAgain"
					@load-task="onLoadTask"
					@new-task="onNewTask"
					@background-notify="onBackgroundNotify"
					@cancel-task="onCancelTask" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'

import AssistantTextProcessingForm from './AssistantTextProcessingForm.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'AssistantTextProcessingModal',
	components: {
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
		initSelectedTaskId: {
			type: [Number, null],
			default: null,
		},
		initInputs: {
			type: Object,
			default: () => {},
		},
		initOutputs: {
			type: [Object, null],
			default: null,
		},
		initSelectedTaskTypeId: {
			type: [String, null],
			default: null,
		},
		actionButtons: {
			type: Array,
			default: () => [],
		},
		taskTypeIdList: {
			type: [Array, null],
			default: null,
		},
	},
	emits: [
		'cancel',
		'cancel-task',
		'background-notify',
		'sync-submit',
		'action-button-clicked',
		'try-again',
		'load-task',
		'new-task',
	],
	data() {
		return {
			show: true,
			closeButtonTitle: t('assistant', 'Close'),
			closeButtonLabel: t('assistant', 'Close Nextcloud Assistant'),
			modalSize: 'full',
			progress: null,
			loading: false,
			expectedRuntime: null,
			isNotifyEnabled: false,
			showSyncTaskRunning: false,
			showScheduleConfirmation: false,
			// from props
			selectedTaskId: this.initSelectedTaskId,
			inputs: this.initInputs,
			outputs: this.initOutputs,
			selectedTaskTypeId: this.initSelectedTaskTypeId,
		}
	},
	computed: {
		shortInput() {
			const input = this.inputs.input ?? this.inputs.sourceMaterial ?? ''
			if (typeof input === 'string') {
				if (input.length <= 200) {
					return input
				}
				return input.slice(0, 200) + '…'
			}
			return ''
		},
	},
	mounted() {
		console.debug('[assistant] modal\'s outputs', this.outputs)
		if (this.isInsideViewer) {
			const elem = this.$refs.modal_content
			emit('viewer:trapElements:changed', elem)
		}
	},
	methods: {
		onCancel() {
			this.show = false
			this.$emit('cancel')
			this.$el.dispatchEvent(new CustomEvent('cancel', { bubbles: true }))
		},
		onCancelTask() {
			this.$emit('cancel-task')
			this.$el.dispatchEvent(new CustomEvent('cancel-task', { bubbles: true }))
		},
		onBackgroundNotify(enable) {
			this.$emit('background-notify', enable)
			this.$el.dispatchEvent(new CustomEvent('background-notify', { detail: enable, bubbles: true }))
		},
		onSyncSubmit(params) {
			this.$emit('sync-submit', params)
			this.$el.dispatchEvent(new CustomEvent('sync-submit', { detail: params, bubbles: true }))
		},
		onActionButtonClicked(data) {
			this.$emit('action-button-clicked', data)
			this.$el.dispatchEvent(new CustomEvent('action-button-clicked', { detail: data, bubbles: true }))
		},
		onNewTask() {
			this.$emit('new-task')
			this.$el.dispatchEvent(new CustomEvent('new-task', { bubbles: true }))
		},
		onTryAgain(data) {
			this.$emit('try-again', data)
			this.$el.dispatchEvent(new CustomEvent('try-again', { detail: data, bubbles: true }))
		},
		onLoadTask(data) {
			this.$emit('load-task', data)
			this.$el.dispatchEvent(new CustomEvent('load-task', { detail: data, bubbles: true }))
		},
	},
}
</script>

<style lang="scss">
// TODO fix this in nc/vue
.modal-container__content .assistant-modal--wrapper {
	height: 100%;
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
	width: 100%;
	display: flex;
	overflow-y: auto;
}

.assistant-modal--content {
	width: 100%;
	max-width: 1200px;
	margin: 0 auto;
	padding: 16px;
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
		height: 100%;
	}
}
</style>
