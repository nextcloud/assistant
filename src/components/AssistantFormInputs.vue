<template>
	<!-- TODO remove that -->
	<div v-if="selectedTaskTypeId === 'copywriter'" class="assistant-inputs">
		<div class="input-container">
			<div class="label-row">
				<label for="writingStyle" class="input-label">
					{{ t('assistant','Writing style') }}
				</label>
				<NcButton
					type="secondary"
					@click="onChooseFile('writingStyle')">
					<template #icon>
						<FileDocumentIcon />
					</template>
					{{ t('assistant','Choose file') }}
				</NcButton>
			</div>
			<NcRichContenteditable
				id="writingStyle"
				:value.sync="writingStyle"
				:link-autocomplete="false"
				:multiline="true"
				class="editable-input"
				:placeholder="t('assistant','Describe the writing style you want to use or supply an example document.')"
				@update:value="onUpdateCopywriter" />
		</div>
		<div class="input-container">
			<div class="label-row">
				<label for="sourceMaterial" class="input-label">
					{{ t('assistant','Source material') }}
				</label>
				<NcButton
					type="secondary"
					@click="onChooseFile('sourceMaterial')">
					<template #icon>
						<FileDocumentIcon />
					</template>
					{{ t('assistant','Choose file') }}
				</NcButton>
			</div>
			<NcRichContenteditable
				id="sourceMaterial"
				:value.sync="sourceMaterial"
				:link-autocomplete="false"
				:multiline="true"
				class="editable-input"
				:placeholder="t('assistant','Describe what you want the document to be written on.')"
				@update:value="onUpdateCopywriter" />
		</div>
	</div>
	<ChattyLLMInputForm v-else-if="selectedTaskTypeId === 'chatty-llm'" class="chatty-inputs" />
	<div v-else class="assistant-inputs">
		<div v-if="selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType'" class="assistant-inputs">
			<NcCheckboxRadioSwitch :checked.sync="sccEnabled" @update:checked="onUpdateContextChat">
				{{ t('assistant', 'Selective context') }}
			</NcCheckboxRadioSwitch>
			<ContextChatInputForm v-if="sccEnabled" :scc-data.sync="sccData" @update:scc-data="onUpdateContextChat" />
		</div>
		<div class="input-container">
			<TaskTypeFields
				:is-output="false"
				:shape="selectedTaskType.inputShape"
				:optional-shape="selectedTaskType.optionalInputShape ?? null"
				:values="inputs"
				@update:values="$emit('update:inputs', $event)" />
		</div>
	</div>
</template>

<script>
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'

import ChattyLLMInputForm from './ChattyLLM/ChattyLLMInputForm.vue'
import ContextChatInputForm from './ContextChat/ContextChatInputForm.vue'
import TaskTypeFields from './fields/TaskTypeFields.vue'

import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

const VALID_MIME_TYPES = [
	'text/rtf',
	'text/plain',
	'text/markdown',
	'application/msword', // doc
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
	'application/vnd.oasis.opendocument.text', // odt
	// 'application/pdf', // Not yet supported
]

const picker = (callback, target) => getFilePickerBuilder(t('assistant', 'Choose a text file'))
	.setMimeTypeFilter(VALID_MIME_TYPES)
	.setMultiSelect(false)
	.allowDirectories(false)
	.addButton({
		id: 'choose-input-file',
		label: t('assistant', 'Choose'),
		type: 'primary',
		callback: callback(target),
	})
	.build()

export default {
	name: 'AssistantFormInputs',
	components: {
		NcRichContenteditable,
		NcButton,
		FileDocumentIcon,
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
	},
	data() {
		return {
			writingStyle: this.inputs.writingStyle ?? '',
			sourceMaterial: this.inputs.sourceMaterial ?? this.inputs.prompt ?? '',
			prompt: this.inputs.prompt ?? '',
			sttMode: 'record',
			sttAudioData: null,
			sttAudioFilePath: this.inputs.audioFilePath ?? null,
			ttiNResults: this.inputs.nResults ?? 1,
			ttiDisplayPrompt: this.inputs.displayPrompt ?? false,
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
			if (this.selectedTaskTypeId === 'copywriter') {
				this.onUpdateCopywriter()
			} else if (this.selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType') {
				this.onUpdateContextChat()
			} else {
				this.resetInputs()
			}
		},
		inputs(newVal) {
			this.writingStyle = this.inputs.writingStyle ?? ''
			this.sourceMaterial = this.inputs.sourceMaterial ?? ''
			this.prompt = this.inputs.prompt ?? ''
			this.sttMode = this.inputs.sttMode ?? 'record'
			this.sttAudioData = this.inputs.audioData ?? null
			this.sttAudioFilePath = this.inputs.audioFilePath ?? null
			this.ttiNResults = this.inputs.nResults ?? 1
			this.ttiDisplayPrompt = this.inputs.displayPrompt ?? false
			this.sccEnabled = !!this.inputs.scopeType && !!this.inputs.scopeList
			this.sccData.sccScopeType = this.inputs.scopeType ?? 'source'
			this.sccData.sccScopeList = this.inputs.scopeList ?? []
			this.sccData.sccScopeListMeta = this.inputs.scopeListMeta ?? []
		},
	},
	mounted() {
		if (this.selectedTaskTypeId === 'copywriter') {
			this.onUpdateCopywriter()
		} else if (this.selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType') {
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
		async onChooseFile(target) {
			await picker(this.parseChosenFile, target).pick()
		},
		parseChosenFile(target) {
			return (nodes) => {
				if (!nodes || nodes.length === 0 || !nodes[0].path) {
					showError(t('assistant', 'No file selected'))
					return
				}

				const url = generateOcsUrl('/apps/assistant/api/v1/parse-file')
				axios.post(url, {
					filePath: nodes[0].path,
				}).then((response) => {
					const data = response.data?.ocs?.data
					if (data?.parsedText === undefined) {
						showError(t('assistant', 'Unexpected response from text parser'))
						return
					}

					switch (target) {
					case 'sourceMaterial':
						this.sourceMaterial = data.parsedText
						this.onUpdateCopywriter()
						break
					case 'writingStyle':
						this.writingStyle = data.parsedText
						this.onUpdateCopywriter()
						break
					default:
					}
				}).catch((error) => {
					console.error(error)
					showError(t('assistant', 'Could not parse file'))
				})
			}
		},
		onUpdateCopywriter() {
			this.$emit('update:inputs', {
				writingStyle: this.writingStyle,
				sourceMaterial: this.sourceMaterial,
			})
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
	width: 100%;
}
</style>
