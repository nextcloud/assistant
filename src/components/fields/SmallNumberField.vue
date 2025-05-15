<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="number-field">
		<label :for="'input-' + fieldKey">
			{{ field.description }}
		</label>
		<div class="line">
			<NcButton v-for="n in [1, 2, 3, 4, 5]"
				:key="n"
				:title="n"
				:type="n === parseFloat(value) ? 'primary' : 'secondary'"
				@click="onButtonClick(n)">
				{{ n }}
			</NcButton>
			<NcButton v-if="!customValue"
				@click="onCustomClicked">
				{{ t('assistant', 'Custom value') }}
			</NcButton>
			<NcInputField v-else
				:id="'input-' + fieldKey"
				ref="input-field"
				class="number-input-field"
				:value="value ?? ''"
				type="text"
				:label-outside="true"
				:title="field.name"
				:placeholder="field.placeholder ?? (field.description || t('assistant','Type some number'))"
				:error="!isValid"
				:helper-text="isValid ? '' : t('assistant', 'The current value is not a number')"
				@update:value="onUpdateInputField" />
		</div>
	</div>
</template>

<script>
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

let timeout
const debounce = (fn, ms = 2000) => {
	clearTimeout(timeout)
	timeout = setTimeout(fn, ms)
}

export default {
	name: 'SmallNumberField',

	components: {
		NcInputField,
		NcButton,
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
			customValue: ![1, 2, 3, 4, 5].includes(parseFloat(this.value)),
		}
	},

	computed: {
		isValid() {
			return this.value === null || this.value === '' || typeof this.value === 'number'
		},
	},

	watch: {
		value(newValue) {
			this.customValue = ![1, 2, 3, 4, 5].includes(parseFloat(newValue))
		},
	},

	mounted() {
	},

	methods: {
		onCustomClicked() {
			this.customValue = true
			this.$nextTick(() => {
				this.$refs['input-field'].focus()
				this.$refs['input-field'].select()
			})
		},
		onButtonClick(value) {
			this.customValue = false
			this.onUpdateValue(value)
		},
		onUpdateInputField(value) {
			debounce(() => {
				this.onUpdateValue(value)
			})
		},
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

	.line {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.number-input-field {
		width: 200px !important;
		margin-top: 0 !important;
	}
}
</style>
