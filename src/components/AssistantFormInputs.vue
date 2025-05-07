<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<ContextChatInputForm v-if="selectedTaskTypeId === 'context_chat:context_chat'"
		:inputs="inputs"
		:task-type="selectedTaskType"
		@update:inputs="$emit('update:inputs', $event)" />
	<div v-else class="assistant-inputs">
		<div class="input-container">
			<TaskTypeFields
				:is-output="false"
				:shape="selectedTaskType.inputShape"
				:optional-shape="selectedTaskType.optionalInputShape ?? null"
				:shape-options="selectedTaskType.inputShapeEnumValues ?? null"
				:optional-shape-options="selectedTaskType.optionalInputShapeEnumValues ?? null"
				:values="inputs"
				:show-advanced="showAdvanced"
				@submit="$emit('submit', $event)"
				@update:show-advanced="$emit('update:show-advanced', $event)"
				@update:values="$emit('update:inputs', $event)" />
		</div>
	</div>
</template>

<script>
import ContextChatInputForm from './ContextChat/ContextChatInputForm.vue'
import TaskTypeFields from './fields/TaskTypeFields.vue'

export default {
	name: 'AssistantFormInputs',
	components: {
		ContextChatInputForm,
		TaskTypeFields,
	},
	props: {
		inputs: {
			type: Object,
			default: () => {},
		},
		selectedTaskId: {
			type: [Number, null],
			default: null,
		},
		selectedTaskType: {
			type: [Object, null],
			default: null,
		},
		showAdvanced: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		selectedTaskTypeId() {
			return this.selectedTaskType?.id ?? null
		},
	},
	watch: {
		selectedTaskType() {
			console.debug('[assistant] watch selectedTaskType', this.selectedTaskType, this.selectedTaskTypeId)
			this.setDefaultValues(true)
		},
	},
	mounted() {
		console.debug('[assistant] mounted AssistantFormInputs', this.selectedTaskId, this.selectedTaskType)
		// don't set the default values if there is a loaded task (initial or from history)
		if (this.selectedTaskType && this.selectedTaskId === null) {
			this.setDefaultValues(false)
		}
	},
	methods: {
		setDefaultValues(clear = true) {
			console.debug('[assistant] set default values', this.selectedTaskType?.inputShapeDefaults, this.selectedTaskType?.optionalInputShapeDefaults)
			const inputs = clear
				? {}
				: {
					...this.inputs,
				}
			// set default values
			if (this.selectedTaskType.inputShapeDefaults) {
				Object.keys(this.selectedTaskType.inputShapeDefaults).forEach(key => {
					if (this.selectedTaskType.inputShapeDefaults[key]) {
						inputs[key] = this.selectedTaskType.inputShapeDefaults[key]
					}
				})
			}
			if (this.selectedTaskType.optionalInputShapeDefaults) {
				Object.keys(this.selectedTaskType.optionalInputShapeDefaults).forEach(key => {
					if (this.selectedTaskType.optionalInputShapeDefaults[key]) {
						inputs[key] = this.selectedTaskType.optionalInputShapeDefaults[key]
					}
				})
			}
			this.$emit('update:inputs', inputs)
		},
	},
}

</script>

<style lang="scss" scoped>
.chatty-inputs {
	margin-top: 8px;
	height: 8000px;
}

.assistant-inputs {
	margin-bottom: 1rem;
}
</style>
