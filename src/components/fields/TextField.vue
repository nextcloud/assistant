<template>
	<TextInput
		:id="fieldKey + '-input'"
		class="text-field"
		:value="value ?? ''"
		:is-output="isOutput"
		:label="field.description"
		:placeholder="field.placeholder ?? field.description"
		:title="field.name"
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
