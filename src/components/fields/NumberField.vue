<template>
	<div class="number-field">
		<NcInputField
			:id="'input-' + fieldKey"
			class="number-input-field"
			:value="value ?? ''"
			:label="field.name"
			:title="field.description"
			type="number"
			:placeholder="field.description || t('assistant','Type some number')"
			@update:value="onUpdateValue" />
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
			type: [Object, Array, String, Number, null],
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
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onUpdateValue(value) {
			const intValue = parseInt(value)
			if (isNaN(intValue)) {
				this.$emit('update:value', null)
			} else {
				this.$emit('update:value', intValue)
			}
		},
	},
}
</script>

<style lang="scss">
.number-field {
	display: flex;
	align-items: end;

	.number-input-field {
		width: 300px;
	}
}
</style>
