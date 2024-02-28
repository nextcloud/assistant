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
	<div v-else class="assistant-inputs">
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

import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import SpeechToTextInputForm from './SpeechToText/SpeechToTextInputForm.vue'
import Text2ImageInputForm from './Text2Image/Text2ImageInputForm.vue'

import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

const VALID_MIME_TYPES = [
	'text/plain',
	'text/markdown',
	'application/msword', // doc
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
	'application/vnd.oasis.opendocument.text', // odt
	// 'application/pdf', // Not yet supported
]

const picker = getFilePickerBuilder(t('assistant', 'Choose a text file'))
	.setMimeTypeFilter(VALID_MIME_TYPES)
	.setMultiSelect(false)
	.allowDirectories(false)
	.setType(1)
	.build()

export default {
	name: 'AssistantFormInputs',
	components: {
		Text2ImageInputForm,
		SpeechToTextInputForm,
		NcRichContenteditable,
		NcButton,
		FileDocumentIcon,
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
		},
	},
	mounted() {
		if (this.selectedTaskTypeId === 'copywriter') {
			this.onUpdateCopywriter()
		} else if (this.selectedTaskTypeId === 'speech-to-text') {
			this.onUpdateStt()
		} else if (this.selectedTaskTypeId === 'OCP\\TextToImage\\Task') {
			this.onUpdateTti()
		} else {
			this.onUpdate()
		}
	},
	methods: {
		async onChooseFile(target) {
			const filePath = await picker.pick()
			if (!filePath) {
				showError(t('assistant', 'No file selected'))
				return
			}
			const url = generateUrl('apps/assistant/parse-file')
			axios.post(url, {
				filePath,
			}).then((response) => {
				if (response.data?.parsedText === undefined) {
					showError(t('assistant', 'Unexpected response from text parser'))
					return
				}
				switch (target) {
				case 'sourceMaterial':
					this.sourceMaterial = response.data.parsedText
					this.onUpdateCopywriter()
					break
				case 'writingStyle':
					this.writingStyle = response.data.parsedText
					this.onUpdateCopywriter()
					break
				default:
					this.prompt = response.data.parsedText
					this.onUpdateMainInput()
				}
			}).catch((error) => {
				console.error(error)
				showError(t('assistant', 'Could not parse file'))
			})
		},
		onUpdateMainInput() {
			if (this.selectedTaskTypeId === 'OCP\\TextToImage\\Task') {
				this.onUpdateTti()
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
	},
}

</script>

<style land="scss" scoped>

.assistant-inputs {
	margin-bottom: 1rem;
	width: 100%;
	.input-container {
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
