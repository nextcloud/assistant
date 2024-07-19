<template>
	<TextInput
		:id="fieldKey + '-input'"
		class="text-field"
		:value="value ?? ''"
		:is-output="isOutput"
		:label="field.description"
		:placeholder="field.placeholder ?? field.description"
		:title="field.name"
		@update:value="$emit('update:value', $event)" />
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
	},
}
</script>

<style lang="scss">
// nothing yet
</style>
