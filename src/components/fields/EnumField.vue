<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="enum-field">
		<label :for="'input-' + fieldKey">
			{{ field.description }}
		</label>
		<NcSelect
			:id="'input-' + fieldKey"
			class="enum-field-input"
			:value="selectValue"
			:options="options"
			:clearable="true"
			label="name"
			:label-outside="true"
			:title="field.name"
			:placeholder="field.placeholder ?? (field.description || t('assistant','Choose a value'))"
			:no-wrap="false"
			@input="onUpdateValue" />
	</div>
</template>

<script>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

export default {
	name: 'EnumField',

	components: {
		NcSelect,
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
		options: {
			type: Array,
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
		selectValue() {
			return this.options.find(option => option.value === this.value)
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onUpdateValue(newValue) {
			if (newValue === null) {
				this.$emit('update:value', undefined)
			} else {
				this.$emit('update:value', newValue.value)
			}
		},
	},
}
</script>

<style lang="scss">
.enum-field {
	display: flex;
	flex-direction: column;
	align-items: start;

	&-input {
		min-width: 300px !important;
		margin-top: 0 !important;
	}
}
</style>
