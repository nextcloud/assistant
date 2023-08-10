<template>
	<div class="assistant-form">
		<span class="assistant-bubble">
			<CreationIcon :size="16" class="icon" />
			<span>{{ t('assistant', 'Nextcloud assistant') }}</span>
		</span>
		<NcSelect
			:value="selectedTaskType"
			class="task-select"
			:options="taskTypes"
			label="name"
			:placeholder="t('assistant', 'Choose a task')"
			input-id="task-select"
			@input="onTaskInput" />
		<h2 v-if="selectedTaskType"
			class="task-name">
			{{ selectedTaskType.name }}
		</h2>
		<span v-if="selectedTaskType"
			class="task-description">
			{{ selectedTaskType.description }}
		</span>
		<NcRichContenteditable
			:value.sync="myInput"
			class="editable-input"
			:multiline="true"
			:disabled="loading"
			:placeholder="t('assistant', 'Type some text')"
			:link-autocomplete="false" />
		<NcRichContenteditable
			v-if="myOutput"
			ref="output"
			:value.sync="myOutput"
			class="editable-output"
			:multiline="true"
			:disabled="loading"
			:placeholder="t('assistant', 'Result')"
			:link-autocomplete="false" />
		<div class="footer">
			<NcButton
				v-if="showSubmit"
				:type="submitButtonType"
				class="submit-button"
				:disabled="!canSubmit"
				:aria-label="t('assistant', 'Submit assistant task')"
				:title="t('assistant', 'Submit')"
				@click="onSubmit">
				{{ submitButtonLabel }}
				<template #icon>
					<CreationIcon />
				</template>
			</NcButton>
			<NcButton
				v-if="showCopy"
				type="primary"
				class="copy-button"
				:aria-label="t('assistant', 'Copy task output')"
				:title="t('assistant', 'Copy')"
				@click="onCopy">
				{{ t('assistant', 'Copy') }}
				<template #icon>
					<ClipboardCheckOutlineIcon v-if="copied" />
					<ContentCopyIcon v-else />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import CreationIcon from 'vue-material-design-icons/Creation.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import VueClipboard from 'vue-clipboard2'
import Vue from 'vue'

Vue.use(VueClipboard)

export default {
	name: 'AssistantForm',
	components: {
		NcButton,
		NcRichContenteditable,
		NcSelect,
		CreationIcon,
		ContentCopyIcon,
		ClipboardCheckOutlineIcon,
	},
	props: {
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
	},
	emits: [
		'cancel',
		'submit',
	],
	data() {
		return {
			myInput: this.input,
			myOutput: this.output,
			loading: false,
			taskTypes: [],
			mySelectedTaskTypeId: this.selectedTaskTypeId,
			copied: false,
		}
	},
	computed: {
		selectedTaskType() {
			if (this.mySelectedTaskTypeId === null) {
				return null
			}
			return this.taskTypes.find(tt => tt.id === this.mySelectedTaskTypeId)
		},
		submitButtonType() {
			return this.myOutput.trim() ? 'secondary' : 'primary'
		},
		showSubmit() {
			return this.selectedTaskType
		},
		canSubmit() {
			return this.selectedTaskType && !!this.myInput.trim()
		},
		submitButtonLabel() {
			return this.myOutput.trim() ? t('assistant', 'Try again') : this.selectedTaskType.name
		},
		showCopy() {
			return !!this.myOutput.trim()
		},
	},
	mounted() {
		this.getTaskTypes()
	},
	methods: {
		getTaskTypes() {
			axios.get(generateOcsUrl('textprocessing/tasktypes', 2))
				.then((response) => {
					this.taskTypes = response.data.ocs.data.types
				})
				.catch((error) => {
					console.error(error)
				})
				.then(() => {
					this.loading = false
				})
		},
		onTaskInput(taskType) {
			this.mySelectedTaskTypeId = taskType?.id ?? null
		},
		onCancel() {
			this.$emit('cancel')
		},
		onSubmit() {
			this.$emit('submit', { input: this.myInput.trim(), taskTypeId: this.mySelectedTaskTypeId })
		},
		async onCopy() {
			try {
				const container = this.$refs.output.$el
				await this.$copyText(this.myOutput.trim(), container)
				this.copied = true
				setTimeout(() => {
					this.copied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Result could not be copied to clipboard'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.assistant-form {
	//width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 12px;
	overflow-y: auto;

	.editable-input,
	.editable-output {
		width: 100%;
		min-height: unset !important;
		max-height: 200px !important;
	}

	.assistant-bubble {
		align-self: start;
		display: flex;
		gap: 8px;
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius-rounded);
		padding: 2px 8px;
		.icon {
			color: var(--color-primary);
		}
	}

	.task-select {
		align-self: start;
		width: 250px;
	}

	.task-name {
		margin-bottom: 0px;
	}

	.task-name,
	.task-description {
		align-self: start;
	}

	.footer {
		width: 100%;
		display: flex;
		justify-content: end;
		gap: 4px;
	}

	.success-icon {
		color: var(--color-success);
	}
}
</style>
