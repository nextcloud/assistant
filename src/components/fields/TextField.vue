<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<TextInput
		:id="fieldKey + '-input'"
		class="text-field"
		:value="value ?? ''"
		:is-output="isOutput"
		:label="field.description"
		:placeholder="field.placeholder ?? field.description"
		:title="field.name"
		@submit="onSubmit"
		@update:value="onUpdateValue" />
</template>

<script>
import TextInput from './TextInput.vue'

export default {
	name: 'TextField',

	components: {
		TextInput,
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
		'submit',
		'update:value',
	],

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onSubmit(event) {
			console.debug('[Assistant] submit task event', event)
			this.$emit('submit', event)
		},
		onUpdateValue(newValue) {
			console.debug('[Assistant] new text value', newValue)
			this.$emit('update:value', newValue?.trim())
		},
	},
}
</script>

<style lang="scss">
// nothing yet
</style>
