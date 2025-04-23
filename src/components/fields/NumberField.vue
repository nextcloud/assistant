<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="number-field">
		<label :for="'input-' + fieldKey">
			{{ field.description }}
		</label>
		<NcInputField
			:id="'input-' + fieldKey"
			class="number-input-field"
			:value="value ?? ''"
			type="number"
			:label-outside="true"
			:title="field.name"
			:placeholder="field.placeholder ?? (field.description || t('assistant','Type some number'))"
			:error="!isValid"
			:helper-text="isValid ? '' : t('assistant', 'The current value is not a number')"
			@update:model-value="onUpdateValue" />
	</div>
</template>

<script>
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'

export default {
	name: 'NumberField',

	components: {
		NcInputField,
	},

	props: {
		fieldKey: {
			type: String,
			required: true,
		},
		value: {
			type: [String, Number, null],
			default: null,
		},
		field: {
			type: Object,
			required: true,
		},
	},

	emits: [
		'update:value',
	],

	data() {
		return {
		}
	},

	computed: {
		isValid() {
			return this.value === null || this.value === '' || typeof this.value === 'number'
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onUpdateValue(value) {
			const numberValue = parseFloat(value)
			if (isNaN(numberValue)) {
				this.$emit('update:value', value)
			} else {
				this.$emit('update:value', numberValue)
			}
		},
	},
}
</script>

<style lang="scss">
.number-field {
	display: flex;
	flex-direction: column;
	align-items: start;

	.number-input-field {
		width: 300px !important;
		margin-top: 0 !important;
	}
}
</style>
