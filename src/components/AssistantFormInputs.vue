<template>
	<div v-if="selectedTaskTypeId === 'copywriter'" class="assistant-inputs">
		<div class="input-container">
			<div class="label-row">
				<label for="writingStyle" class="input-label">
					{{ t('assistant','Writing style') }}
				</label>
				<NcButton
					type="secondary"
					@click="onChooseFile('writingStyle')">
					{{ t('assistant','Choose File') }}
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
					{{ t('assistant','Choose File') }}
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
					{{ t('assistant','Choose File') }}
				</NcButton>
			</div>
			<NcRichContenteditable
				id="input-prompt"
				:value.sync="prompt"
				:link-autocomplete="false"
				:multiline="true"
				class="editable-input"
				:placeholder="t('assistant','Type some text')"
				@update:value="onUpdate" />
		</div>
	</div>
</template>

<script>
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
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
		NcRichContenteditable,
		NcButton,
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
			writingStyle: '',
			sourceMaterial: '',
			prompt: '',
		}
	},
	watch: {
		selectedTaskTypeId() {
			if (this.selectedTaskTypeId === 'copywriter') {
				this.onUpdateCopywriter()
			} else {
				this.onUpdate()
			}
		},
	},
	mounted() {
		this.writingStyle = this.inputs.writingStyle ?? ''
		this.sourceMaterial = this.inputs.sourceMaterial ?? this.inputs.prompt ?? ''
		this.prompt = this.inputs.prompt ?? ''

		if (this.selectedTaskTypeId === 'copywriter') {
			this.onUpdateCopywriter()
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
					this.onUpdate()
				}
			}).catch((error) => {
				console.error(error)
				showError(t('assistant', 'Could not parse file'))
			})
		},
		onUpdate() {
			this.$emit('update:newInputs', {
				prompt: this.prompt,
			})
		},
		onUpdateCopywriter() {
			this.$emit('update:newInputs', {
				writingStyle: this.writingStyle,
				sourceMaterial: this.sourceMaterial,
			})
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
