<template>
	<ChattyLLMInputForm v-if="selectedTaskTypeId === 'chatty-llm'" class="chatty-inputs" />
	<ContextChatInputForm v-else-if="selectedTaskTypeId === 'context_chat:context_chat'"
		:inputs="inputs"
		@update:inputs="$emit('update:inputs', $event)" />
	<div v-else class="assistant-inputs">
		<div class="input-container">
			<TaskTypeFields
				:is-output="false"
				:shape="selectedTaskType.inputShape"
				:optional-shape="selectedTaskType.optionalInputShape ?? null"
				:values="inputs"
				:show-advanced="showAdvanced"
				@update:show-advanced="$emit('update:show-advanced', $event)"
				@update:values="$emit('update:inputs', $event)" />
		</div>
	</div>
</template>

<script>
import ChattyLLMInputForm from './ChattyLLM/ChattyLLMInputForm.vue'
import ContextChatInputForm from './ContextChat/ContextChatInputForm.vue'
import TaskTypeFields from './fields/TaskTypeFields.vue'

export default {
	name: 'AssistantFormInputs',
	components: {
		ContextChatInputForm,
		ChattyLLMInputForm,
		TaskTypeFields,
	},
	props: {
		inputs: {
			type: Object,
			default: () => {},
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
			console.debug('aaaa watch selectedTaskType', this.selectedTaskType, this.selectedTaskTypeId)
			this.resetInputs()
		},
	},
	mounted() {
	},
	methods: {
		resetInputs() {
			const inputs = {}
			Object.keys(this.selectedTaskType.inputShape).forEach(key => {
				inputs[key] = null
			})
			this.$emit('update:inputs', inputs)
			// TODO do it with optional input shape as well
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
	//width: 100%;
}
</style>
