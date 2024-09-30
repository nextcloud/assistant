<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="editable-text-field" class="editable-text-field">
		<NcRichText v-if="!editing"
			class="editable-text-field__output"
			dir="auto"
			:text="text"
			:autolink="false"
			:use-extended-markdown="true" />
		<NcTextField v-else
			ref="ncTextField"
			v-tooltip="t('assistant', 'The text must be shorter than or equal to {maxLength} characters, currently {length}', { maxLength, length: text.length })"
			dir="auto"
			:value.sync="text"
			:maxlength="maxLength"
			:disabled="loading"
			:placeholder="placeholder"
			:label-outside="true"
			@keydown.enter="handleSubmitText"
			@keydown.esc="handleCancelEditing" />
		<template v-if="!loading">
			<template v-if="editing">
				<NcButton type="tertiary"
					:aria-label="t('assistant', 'Cancel editing')"
					@click="handleCancelEditing">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
				<NcButton type="primary"
					:aria-label="t('assistant', 'Submit')"
					:disabled="!canSubmit"
					@click="handleSubmitText">
					<template #icon>
						<Check :size="20" />
					</template>
				</NcButton>
			</template>
		</template>
		<div v-if="loading" class="icon-loading-small spinner" />
	</div>
</template>

<script>
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { NcRichText } from '@nextcloud/vue/dist/Components/NcRichText.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

import { parseSpecialSymbols } from '../../utils.js'

export default {
	name: 'EditableTextField',
	components: {
		Check,
		Close,
		NcButton,
		NcTextField,
		NcRichText,
	},

	directives: {
		Tooltip,
	},

	props: {
		/**
		 * The "outer" value of the text, coming from the store. Every time this changes,
		 * the text value in this component is overwritten.
		 */
		initialText: {
			type: String,
			default: '',
		},

		/**
		 * Toggles the text editing state on and off.
		 */
		editing: {
			type: Boolean,
			default: false,
		},

		/**
		 * Placeholder for the contenteditable element.
		 */
		placeholder: {
			type: String,
			default: '',
		},

		/**
		 * Toggles the loading state on and off.
		 */
		loading: {
			type: Boolean,
			default: false,
		},

		/**
		 * Maximum text length in characters
		 */
		maxLength: {
			type: Number,
			default: 500,
		},
	},

	emits: ['update:editing', 'submit-text'],

	data() {
		return {
			text: this.initialText,
		}
	},

	watch: {
		// Each time the prop changes, reflect the changes in the value stored in this component
		initialText(newValue) {
			this.text = newValue
		},

		editing(newValue) {
			if (!newValue) {
				this.text = this.initialText
			} else {
				this.$nextTick(() => {
					this.$refs.ncTextField.focus()
				})
			}
		},
	},

	methods: {
		canSubmit() {
			return this.text.length <= this.maxLength && this.text !== this.initialText
		},

		handleSubmitText() {
			if (!this.canSubmit()) {
				return
			}

			// Parse special symbols
			this.text = parseSpecialSymbols(this.text)

			// Submit text
			this.$emit('submit-text', this.text)
		},

		handleCancelEditing() {
			this.text = this.initialText
			this.$emit('update:editing', false)
		},
	},
}

</script>

<style lang="scss" scoped>
.editable-text-field {
	display: flex;
	//width: 100%;
	overflow: hidden;
	position: relative;
	min-height: var(--default-clickable-area);
	align-items: center;
	gap: 4px;

	> div.input-field {
		margin: 0 0 0 6px !important;
	}

	&__edit {
		margin-left: var(--default-clickable-area);
	}

	&__output {
		width: 100%;
		padding: 10px;
		margin: 0 !important;
		line-height: var(--default-line-height) !important;
	}

}

.spinner {
	width: var(--default-clickable-area);
	height: var(--default-clickable-area);
	margin: 0 0 0 44px;
}
</style>
