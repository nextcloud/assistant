<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<PrimeDialog v-model:visible="show"
		maximizable
		:closable="false"
		:dismissable-mask="false"
		:close-on-escape="false"
		:draggable="true"
		append-to="body"
		:base-z-index="5000"
		class="assistant-modal">
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
					:task-status="taskStatus"
					:scheduled-at="scheduledAt"
					@sync-submit="onSyncSubmit"
					@action-button-clicked="onActionButtonClicked"
					@try-again="onTryAgain"
					@load-task="onLoadTask"
					@new-task="onNewTask"
					@background-notify="onBackgroundNotify"
					@cancel-task="onCancelTask" />
			</div>
		</div>
	</PrimeDialog>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import PrimeDialog from 'primevue/dialog'

import NcButton from '@nextcloud/vue/components/NcButton'

import AssistantTextProcessingForm from './AssistantTextProcessingForm.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'AssistantTextProcessingModal',
	components: {
		AssistantTextProcessingForm,
		PrimeDialog,
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
			eventTarget: null,
			closeButtonTitle: t('assistant', 'Close'),
			closeButtonLabel: t('assistant', 'Close Nextcloud Assistant'),
			progress: null,
			taskStatus: null,
			scheduledAt: null,
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
		this.eventTarget = this.$el?.parentElement ?? null
		if (this.isInsideViewer) {
			const elem = this.$refs.modal_content
			emit('viewer:trapElements:changed', elem)
		}
	},
	methods: {
		dispatchModalEvent(name, detail) {
			const target = this.eventTarget ?? this.$refs.modal_content
			if (!target) {
				return
			}

			target.dispatchEvent(new CustomEvent(name, {
				detail,
				bubbles: true,
			}))
		},
		onCancel() {
			this.show = false
			this.$emit('cancel')
			this.dispatchModalEvent('cancel')
		},
		onCancelTask() {
			this.$emit('cancel-task')
			this.dispatchModalEvent('cancel-task')
		},
		onBackgroundNotify(enable) {
			this.$emit('background-notify', enable)
			this.dispatchModalEvent('background-notify', enable)
		},
		onSyncSubmit(params) {
			this.$emit('sync-submit', params)
			this.dispatchModalEvent('sync-submit', params)
		},
		onActionButtonClicked(data) {
			this.$emit('action-button-clicked', data)
			this.dispatchModalEvent('action-button-clicked', data)
		},
		onNewTask() {
			this.$emit('new-task')
			this.dispatchModalEvent('new-task')
		},
		onTryAgain(data) {
			this.$emit('try-again', data)
			this.dispatchModalEvent('try-again', data)
		},
		onLoadTask(data) {
			this.$emit('load-task', data)
			this.dispatchModalEvent('load-task', data)
		},
	},
}
</script>

<style lang="scss">
.assistant-modal.p-dialog {
	position: relative;
	max-width: 100%;
	height: calc(100vh - 32px);
	max-height: calc(100vh - 32px);
	resize: both;
	overflow: hidden;
	// z-index: 100000 !important;
}

.assistant-modal .p-dialog-header {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	z-index: 2;
	min-height: 0;
	padding: 4px;
	border: 0;
	background: transparent;
	cursor: grab;
	&:active {
		cursor: grabbing;
	}
	.p-dialog-maximize-button {
		border-radius: var(--border-radius-element);
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		&:hover {
			background-color: var(--color-background-hover);
			border: none;
		}
	}
}

.assistant-modal .p-dialog-content {
	padding: 0;
	display: flex;
	flex: 1 1 auto;
	height: 100%;
	min-height: 0;
	overflow: hidden;
}

// the smart picker provider selector is not visible in 33
div[role='listbox'] {
	z-index: 100000;
}
</style>

<style lang="scss" scoped>
.close-button {
	position: absolute;
	top: 10px;
	right: 10px;
	z-index: 3;
	background-color: var(--color-main-background);
}

.assistant-modal--wrapper {
	width: 100%;
	display: flex;
	flex: 1 1 auto;
	height: 100%;
	min-height: 0;
	overflow: hidden;
}

.assistant-modal--content {
	width: 100%;
	margin: 0 auto;
	padding: 8px 16px 16px 16px;
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: flex-start;
	flex: 1 1 auto;
	height: 100%;
	max-height: 100%;
	min-height: 0;
	overflow: hidden;

	> h2 {
		display: flex;
		margin: 12px 0 20px 0;
		.icon {
			margin-right: 8px;
		}
	}

	.form {
		width: 100%;
		flex: 1 1 auto;
		height: 100%;
		min-height: 0;
		overflow: hidden;
	}
}
</style>
