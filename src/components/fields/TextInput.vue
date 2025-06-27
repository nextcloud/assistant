<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="text-input">
		<label :for="id">
			{{ label }}
		</label>
		<NcRichContenteditable
			:id="id"
			ref="input"
			:model-value="value ?? ''"
			:link-autocomplete="false"
			:multiline="isMobile"
			class="editable-input"
			:class="{ shadowed: isOutput }"
			:placeholder="placeholder"
			:title="title"
			@submit="hasValue && $emit('submit', $event)"
			@update:model-value="$emit('update:value', $event)" />
		<NcButton v-if="isOutput && hasValue"
			class="copy-button"
			variant="secondary"
			:title="t('assistant', 'Copy output')"
			@click="onCopy">
			<template #icon>
				<ClipboardCheckOutlineIcon v-if="copied" />
				<ContentCopyIcon v-else />
			</template>
			{{ t('assistant', 'Copy') }}
		</NcButton>
		<NcButton v-if="!isOutput && !hasValue && showChooseButton"
			class="choose-file-button"
			variant="secondary"
			@click="onChooseFile">
			<template #icon>
				<FileDocumentOutlineIcon />
			</template>
			{{ t('assistant','Choose file') }}
		</NcButton>
	</div>
</template>

<script>
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'

import isMobile from '../../mixins/isMobile.js'

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
	'application/pdf', // pdf
]

const picker = (callback, target) => getFilePickerBuilder(t('assistant', 'Choose a text file'))
	.setMimeTypeFilter(VALID_MIME_TYPES)
	.setMultiSelect(false)
	.allowDirectories(false)
	.addButton({
		id: 'choose-input-file',
		label: t('assistant', 'Choose'),
		variant: 'primary',
		callback: callback(target),
	})
	.build()

export default {
	name: 'TextInput',

	components: {
		NcRichContenteditable,
		NcButton,
		FileDocumentOutlineIcon,
		ClipboardCheckOutlineIcon,
		ContentCopyIcon,
	},

	mixins: [
		isMobile,
	],

	props: {
		id: {
			type: String,
			default: 'noid',
		},
		value: {
			type: String,
			default: '',
		},
		label: {
			type: String,
			default: '',
		},
		placeholder: {
			type: String,
			default: '',
		},
		title: {
			type: String,
			default: '',
		},
		isOutput: {
			type: Boolean,
			default: false,
		},
		showChooseButton: {
			type: Boolean,
			default: true,
		},
	},

	emits: [
		'submit',
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
		hasValue() {
			return this.formattedValue !== ''
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
				await navigator.clipboard.writeText(this.formattedValue)
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
.choose-file-button {
	right: 2px;
	left: unset;
}

body[dir="rtl"] .choose-file-button {
	left: 2px;
	right: unset;
}

.text-input {
	position: relative;

	.copy-button,
	.choose-file-button {
		position: absolute !important;
	}

	.choose-file-button {
		bottom: 2px;
	}

	.copy-button {
		bottom: 4px;
		right: 4px;
	}

	.rich-contenteditable__input {
		min-height: calc(var(--default-clickable-area) + 4px);
		padding-top: 5px !important;
		padding-bottom: 4px !important;
	}
	.shadowed .rich-contenteditable__input {
		border: 2px solid var(--color-primary-element) !important;
		padding-bottom: 38px !important;
	}
}
</style>
