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
				<NcEmptyContent
					v-if="showScheduleConfirmation"
					:title="t('assistant', 'Your task has been scheduled, you will receive a notification when it has finished')"
					:name="t('assistant', 'Your task has been scheduled, you will receive a notification when it has finished')"
					:description="shortInput">
					<template #action>
						<NcButton
							@click="onCancel">
							<template #icon>
								<CloseIcon />
							</template>
							{{ t('assistant', 'Close') }}
						</NcButton>
					</template>
					<template #icon>
						<AssistantIcon />
					</template>
				</NcEmptyContent>
				<AssistantForm
					v-else
					class="form"
					:input="input"
					:output="output"
					:selected-task-type-id="selectedTaskTypeId"
					@cancel="onCancel"
					@submit="onSubmit" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import AssistantIcon from './icons/AssistantIcon.vue'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import AssistantForm from './AssistantForm.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'AssistantModal',
	components: {
		AssistantIcon,
		AssistantForm,
		NcModal,
		NcButton,
		NcEmptyContent,
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
		input: {
			type: String,
			default: '',
		},
		output: {
			type: String,
			default: '',
		},
		selectedTaskTypeId: {
			type: [String, null],
			default: null,
		},
		showScheduleConfirmation: {
			type: Boolean,
			required: true,
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
		shortInput() {
			if (this.input.length <= 200) {
				return this.input
			}
			return this.input.slice(0, 200) + '…'
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
	},
}
</script>

<style lang="scss">
// this is to avoid scroll on the container and leave it to the result block
.assistant-modal .modal-container {
	display: flex !important;

	&__content {
		padding: 16px;
	}
}
</style>

<style lang="scss" scoped>
.close-button {
	position: absolute;
	top: 4px;
	right: 4px;
}

.assistant-modal--wrapper {
	width: 100%;
	// padding: 16px;
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
