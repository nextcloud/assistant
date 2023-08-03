<template>
	<div class="assistant-form">
		<span class="assistant-bubble">
			<CreationIcon :size="16" class="icon" />
			<span>{{ t('textprocessing_assistant', 'Nextcloud assistant') }}</span>
		</span>
		<NcSelect
			:value="selectedTaskType"
			class="task-select"
			:options="taskTypes"
			label="name"
			input-id="task-select"
			@input="onTaskInput" />
		<span v-if="selectedTaskType"
			class="task-description">
			{{ selectedTaskType.description }}
		</span>
		<NcRichContenteditable
			:value.sync="myInput"
			class="editable-input"
			:multiline="true"
			:disabled="loading"
			:placeholder="t('textprocessing_assistant', 'Type some text')"
			:link-autocomplete="false" />
		<NcRichContenteditable
			:value.sync="myOutput"
			class="editable-output"
			:multiline="true"
			:disabled="loading"
			:placeholder="t('textprocessing_assistant', 'Result')"
			:link-autocomplete="false" />
		<NcButton v-if="selectedTaskType"
			class="submit-button"
			:aria-label="t('textprocessing_assistant', 'Submit assistant task')"
			:title="t('textprocessing_assistant', 'Submit')"
			@click="onSubmit">
			{{ selectedTaskType.name }}
			<template #icon>
				<CreationIcon />
			</template>
		</NcButton>
	</div>
</template>

<script>
import CreationIcon from 'vue-material-design-icons/Creation.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'AssistantForm',
	components: {
		NcButton,
		NcRichContenteditable,
		NcSelect,
		CreationIcon,
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
		}
	},
	computed: {
		selectedTaskType() {
			if (this.mySelectedTaskTypeId === null) {
				return null
			}
			return this.taskTypes.find(tt => tt.id === this.mySelectedTaskTypeId)
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
			this.$emit('submit', { input: this.myInput, taskTypeId: this.mySelectedTaskTypeId })
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
		min-height: 150px;
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

	.task-description {
		align-self: start;
	}

	.submit-button {
		align-self: end;
	}
}
</style>
