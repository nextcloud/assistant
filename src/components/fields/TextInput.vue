<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="text-input">
		<label :for="id">
			{{ label }}
			<br v-if="limitLabel">
			{{ limitLabel ?? '' }}
		</label>
		<NcRichContenteditable
			:id="id"
			ref="input"
			:model-value="value ?? ''"
			:link-autocomplete="false"
			:multiline="isMobile"
			:maxlength="maxLength"
			class="editable-input"
			:class="{ shadowed: isOutput, streaming: isOutput && streaming() }"
			:placeholder="placeholder"
			:title="title"
			@submit="hasValue && $emit('submit', $event)"
			@update:model-value="$emit('update:value', $event)" />
		<div v-if="isOutput && hasValue"
			class="output-buttons">
			<NcButton v-if="!streaming() && canImproveOutput"
				class="improve-button"
				variant="secondary"
				:title="t('assistant', 'Improve with new instructions')"
				@click="$emit('improve', formattedValue)">
				<template #icon>
					<WrenchOutlineIcon size="20" />
				</template>
				{{ t('assistant', 'Improve this text') }}
			</NcButton>
			<NcButton
				class="copy-button"
				variant="secondary"
				:title="t('assistant', 'Copy output')"
				@click="onCopy">
				<template #icon>
					<NcLoadingIcon v-if="streaming()" size="20" />
					<ClipboardCheckOutlineIcon v-else-if="copied" size="20" />
					<ContentCopyIcon v-else size="20" />
				</template>
				<span v-if="streaming()">
					{{ t('assistant', 'Getting results...') }}
				</span>
				<span v-else>
					{{ t('assistant', 'Copy') }}
				</span>
			</NcButton>
		</div>
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
import WrenchOutlineIcon from 'vue-material-design-icons/WrenchOutline.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import { NcLoadingIcon } from '@nextcloud/vue'

import isMobile from '../../mixins/isMobile.js'

import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import { VALID_TEXT_MIME_TYPES, MAX_TEXT_INPUT_LENGTH } from '../../constants.js'

const picker = (callback, target) => getFilePickerBuilder(t('assistant', 'Choose a text file'))
	.setMimeTypeFilter(VALID_TEXT_MIME_TYPES)
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
		NcLoadingIcon,
		FileDocumentOutlineIcon,
		ClipboardCheckOutlineIcon,
		ContentCopyIcon,
		WrenchOutlineIcon,
	},

	mixins: [
		isMobile,
	],

	inject: ['streaming'],

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
		canImproveOutput: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'submit',
		'update:value',
		'improve',
	],

	data() {
		return {
			copied: false,
			maxLength: MAX_TEXT_INPUT_LENGTH,
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
		limitLabel() {
			const length = this.value?.length ?? 0
			return length > this.maxLength
				? t('assistant', 'Warning: The input text exceeds the maximum length of {limit} characters (currently {length}).', { length, limit: this.maxLength })
				: undefined
		},
	},

	watch: {
		value(newValue) {
			if (!this.streaming()) {
				return
			}
			const scrollableArea = this.$refs.input?.$el?.querySelector('#' + this.id)
			if (scrollableArea) {
				scrollableArea.scrollTo(0, scrollableArea.scrollHeight)
			}
		},
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

body[dir="rtl"] .output-buttons {
	left: 4px;
	right: unset;
}

.text-input {
	position: relative;

	.output-buttons,
	.choose-file-button {
		position: absolute !important;
	}

	.output-buttons {
		bottom: 4px;
		right: 4px;
		display: flex;
		gap: 4px;
	}

	.choose-file-button {
		bottom: 2px;
	}

	.rich-contenteditable__input {
		min-height: calc(var(--default-clickable-area) * 3 + 4px);
		padding-top: 5px !important;
		padding-bottom: 4px !important;
	}
	.shadowed .rich-contenteditable__input {
		border: 2px solid var(--color-primary-element);
		padding-bottom: 38px !important;
	}
	.shadowed.streaming .rich-contenteditable__input {
		animation: pulse 2s infinite;
	}
	@media (prefers-reduced-motion: reduce) {
		.shadowed.streaming .rich-contenteditable__input {
			animation: none;
		}
	}
}

@keyframes pulse {
	0% {
		box-shadow: 0 0 0 0px rgba(128, 128, 128, 0.7);
	}
	70% {
		box-shadow: 0 0 0 14px rgba(128, 128, 128, 0);
	}
	100% {
		box-shadow: 0 0 0 0px rgba(128, 128, 128, 0);
	}
}
</style>
