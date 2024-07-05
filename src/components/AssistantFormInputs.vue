<template>
	<ChattyLLMInputForm v-if="selectedTaskTypeId === 'chatty-llm'" class="chatty-inputs" />
	<div v-else-if="selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType'" class="assistant-inputs">
		<NcCheckboxRadioSwitch :checked.sync="sccEnabled" @update:checked="onUpdateContextChat">
			{{ t('assistant', 'Selective context') }}
		</NcCheckboxRadioSwitch>
		<ContextChatInputForm v-if="sccEnabled" :scc-data.sync="sccData" @update:scc-data="onUpdateContextChat" />
		<!-- TODO add text input field for context chat -->
	</div>
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import ChattyLLMInputForm from './ChattyLLM/ChattyLLMInputForm.vue'
import ContextChatInputForm from './ContextChat/ContextChatInputForm.vue'
import TaskTypeFields from './fields/TaskTypeFields.vue'

export default {
	name: 'AssistantFormInputs',
	components: {
		NcCheckboxRadioSwitch,
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
			sccEnabled: !!this.inputs.scopeType && !!this.inputs.scopeList,
			sccData: {
				sccScopeType: this.inputs.scopeType ?? 'source',
				sccScopeList: this.inputs.scopeList ?? [],
				sccScopeListMeta: this.inputs.scopeListMeta ?? [],
			},
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
			if (this.selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType') {
				this.onUpdateContextChat()
			} else {
				this.resetInputs()
			}
		},
		inputs(newVal) {
			this.sccEnabled = !!this.inputs.scopeType && !!this.inputs.scopeList
			this.sccData.sccScopeType = this.inputs.scopeType ?? 'source'
			this.sccData.sccScopeList = this.inputs.scopeList ?? []
			this.sccData.sccScopeListMeta = this.inputs.scopeListMeta ?? []
		},
	},
	mounted() {
		if (this.selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType') {
			this.onUpdateContextChat()
		} else {
			// this.resetInputs()
		}
	},
	methods: {
		resetInputs() {
			const inputs = {}
			Object.keys(this.selectedTaskType.inputShape).forEach(key => {
				inputs[key] = null
			})
			this.$emit('update:inputs', inputs)
		},
		onUpdateContextChat() {
			this.$emit(
				'update:inputs',
				{
					prompt: this.prompt,
					...(this.sccEnabled && {
						scopeType: this.sccData.sccScopeType,
						scopeList: this.sccData.sccScopeList,
						scopeListMeta: this.sccData.sccScopeListMeta,
					}),
				},
			)
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
