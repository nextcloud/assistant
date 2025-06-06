<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal v-if="show"
		:size="modalSize"
		:can-close="false"
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
					type="tertiary"
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
					@sync-submit="onSyncSubmit"
					@action-button-clicked="onActionButtonClicked"
					@try-again="$emit('try-again', $event)"
					@load-task="$emit('load-task', $event)"
					@new-task="$emit('new-task')"
					@background-notify="$emit('background-notify')"
					@cancel-task="$emit('cancel-task')" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

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
		loading: {
			type: Boolean,
			default: false,
		},
		selectedTaskId: {
			type: [Number, null],
			default: null,
		},
		inputs: {
			type: Object,
			default: () => {},
		},
		outputs: {
			type: [Object, null],
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
		progress: {
			type: [Number, null],
			default: null,
		},
		expectedRuntime: {
			type: [Number, null],
			default: null,
		},
		actionButtons: {
			type: Array,
			default: () => [],
		},
	},
	emits: [
		'cancel',
		'cancel-task',
		'background-notify',
		'submit',
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
		},
		onSyncSubmit(params) {
			this.$emit('sync-submit', params)
		},
		onActionButtonClicked(data) {
			this.$emit('action-button-clicked', data)
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
