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
	flex-direction: column;
	align-items: start;

	.number-input-field {
		width: 300px;
	}
}
</style>
