<template>
	<div class="text-field">
		<div class="text-field__label-row">
			<label :for="'field-' + fieldKey" class="field-label">
				{{ field.name }}
			</label>
			<label :for="'field-' + fieldKey" class="field-label">
				{{ field.description }}
			</label>
			<NcButton v-if="isOutput"
				type="secondary"
				:title="t('assistant', 'Copy output')"
				@click="onCopy">
				<template #icon>
					<ClipboardCheckOutlineIcon v-if="copied" />
					<ContentCopyIcon v-else />
				</template>
				{{ t('assistant', 'Copy') }}
			</NcButton>
			<NcButton v-else
				type="secondary"
				@click="onChooseFile">
				<template #icon>
					<FileDocumentIcon />
				</template>
				{{ t('assistant','Choose file') }}
			</NcButton>
		</div>
		<NcRichContenteditable
			:id="'field-' + fieldKey"
			ref="field"
			:value="value ?? ''"
			:link-autocomplete="false"
			:multiline="true"
			class="editable-input"
			:class="{ shadowed: isOutput }"
			:placeholder="t('assistant','Type some text')"
			@update:value="$emit('update:value', $event)" />
	</div>
</template>

<script>
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'

import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import VueClipboard from 'vue-clipboard2'
import Vue from 'vue'
Vue.use(VueClipboard)

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
	name: 'TextField',

	components: {
		NcRichContenteditable,
		NcButton,
		FileDocumentIcon,
		ClipboardCheckOutlineIcon,
		ContentCopyIcon,
	},

	props: {
		fieldKey: {
			type: String,
			required: true,
		},
		value: {
			type: [String, null],
			default: null,
		},
		field: {
			type: Object,
			required: true,
		},
		isOutput: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:value',
	],

	data() {
		return {
			copied: false,
		}
	},

	computed: {
		formattedValue() {
			if (this.value) {
				return this.value.trim()
			}
			return ''
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		async onChooseFile() {
			await picker(this.parseChosenFile).pick()
		},
		parseChosenFile() {
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

					this.$emit('update:value', data?.parsedText)
				}).catch((error) => {
					console.error(error)
					showError(t('assistant', 'Could not parse file'))
				})
			}
		},
		async onCopy() {
			try {
				const container = this.$refs.field.$el ?? this.$refs.field
				await this.$copyText(this.formattedValue, container)
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

<style lang="scss">
.text-field {
	&__label-row {
		display: flex;
		flex-direction: row;
		justify-content: space-between;
		margin-bottom: 12px;
		align-items: center;

		.field-label {
			font-weight: bold;
		}
	}

	.shadowed {
		padding: 10px;
		> div, > div:focus, > div:hover {
			box-shadow: 0 0 10px var(--color-primary);
			border: 0;
		}
	}
}
</style>
