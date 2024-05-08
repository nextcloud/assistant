<template>
	<div v-if="selectedTaskTypeId === 'speech-to-text'" class="assistant-inputs">
		<SpeechToTextInputForm
			:mode.sync="sttMode"
			:audio-data.sync="sttAudioData"
			:audio-file-path.sync="sttAudioFilePath"
			@update:mode="onUpdateStt"
			@update:audio-data="onUpdateStt"
			@update:audio-file-path="onUpdateStt" />
	</div>
	<div v-else-if="selectedTaskTypeId === 'copywriter'" class="assistant-inputs">
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
	<div v-else-if="selectedTaskTypeId === 'chatty-llm'" class="assistant-inputs" style="margin-bottom: 0 !important;">
		<ChattyLLMInputForm />
	</div>
	<div v-else class="assistant-inputs">
		<div v-if="selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType'" class="assistant-inputs">
			<NcCheckboxRadioSwitch :checked.sync="sccEnabled" @update:checked="onUpdateContextChat">
				{{ t('assistant', 'Selective context') }}
			</NcCheckboxRadioSwitch>
			<ContextChatInputForm v-if="sccEnabled" :scc-data.sync="sccData" @update:scc-data="onUpdateContextChat" />
		</div>
		<div class="input-container">
			<div class="label-row">
				<label for="input-prompt" class="input-label">
					{{ t('assistant','Input') }}
				</label>
				<NcButton
					type="secondary"
					@click="onChooseFile('prompt')">
					<template #icon>
						<FileDocumentIcon />
					</template>
					{{ t('assistant','Choose file') }}
				</NcButton>
			</div>
			<NcRichContenteditable
				id="input-prompt"
				:value.sync="prompt"
				:link-autocomplete="false"
				:multiline="true"
				class="editable-input"
				:placeholder="t('assistant','Type some text')"
				@update:value="onUpdateMainInput" />
		</div>
		<div v-if="selectedTaskTypeId === 'OCP\\TextToImage\\Task'" class="assistant-inputs">
			<Text2ImageInputForm
				:n-results.sync="ttiNResults"
				:display-prompt.sync="ttiDisplayPrompt"
				@update:nResults="onUpdateTti"
				@update:displayPrompt="onUpdateTti" />
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
import SpeechToTextInputForm from './SpeechToText/SpeechToTextInputForm.vue'
import Text2ImageInputForm from './Text2Image/Text2ImageInputForm.vue'

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
		Text2ImageInputForm,
		SpeechToTextInputForm,
		ContextChatInputForm,
		ChattyLLMInputForm,
	},
	props: {
		inputs: {
			type: Object,
			default: () => {},
		},
		selectedTaskTypeId: {
			type: [String, null],
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
	watch: {
		selectedTaskTypeId() {
			if (this.selectedTaskTypeId === 'copywriter') {
				this.onUpdateCopywriter()
			} else if (this.selectedTaskTypeId === 'speech-to-text') {
				this.onUpdateStt()
			} else if (this.selectedTaskTypeId === 'OCP\\TextToImage\\Task') {
				this.onUpdateTti()
			} else if (this.selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType') {
				this.onUpdateContextChat()
			} else {
				this.onUpdate()
			}
		},
		inputs(newVal) {
			this.writingStyle = this.inputs.writingStyle ?? ''
			this.sourceMaterial = this.inputs.sourceMaterial ?? this.inputs.prompt ?? ''
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
		} else if (this.selectedTaskTypeId === 'speech-to-text') {
			this.onUpdateStt()
		} else if (this.selectedTaskTypeId === 'OCP\\TextToImage\\Task') {
			this.onUpdateTti()
		} else if (this.selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType') {
			this.onUpdateContextChat()
		} else {
			this.onUpdate()
		}
	},
	methods: {
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
						this.prompt = data.parsedText
						this.onUpdateMainInput()
					}
				}).catch((error) => {
					console.error(error)
					showError(t('assistant', 'Could not parse file'))
				})
			}
		},
		onUpdateMainInput() {
			if (this.selectedTaskTypeId === 'OCP\\TextToImage\\Task') {
				this.onUpdateTti()
			} else if (this.selectedTaskTypeId === 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType') {
				this.onUpdateContextChat()
			} else {
				this.onUpdate()
			}
		},
		onUpdate() {
			this.$emit('update:inputs', {
				prompt: this.prompt,
			})
		},
		onUpdateCopywriter() {
			this.$emit('update:inputs', {
				writingStyle: this.writingStyle,
				sourceMaterial: this.sourceMaterial,
			})
		},
		onUpdateStt() {
			this.$emit(
				'update:inputs',
				{
					sttMode: this.sttMode,
					audioData: this.sttAudioData,
					audioFilePath: this.sttAudioFilePath,
				},
			)
		},
		onUpdateTti() {
			this.$emit(
				'update:inputs',
				{
					prompt: this.prompt,
					nResults: this.ttiNResults,
					displayPrompt: this.ttiDisplayPrompt,
				},
			)
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

.assistant-inputs {
	margin-bottom: 1rem;
	width: 100%;

	/* todo: maybe not required now */
	::v-deep .input-container {
		border-radius: var(--border-radius-rounded);
		padding: 12px;

		&:hover {
			background-color: var(--color-primary-element-light);
		}

		.label-row {
			display: flex;
			flex-direction: row;
			justify-content: space-between;
			margin-bottom: 12px;
			> .input-label {
				margin-bottom: -12px;
			}

			.input-label {
				align-self: center;
				font-weight: bold;
			}
		}

		.editable-input {
			min-height: unset !important;
		}
	}
}

</style>
