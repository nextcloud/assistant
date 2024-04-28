<template>
	<div class="number-field">
		<NcInputField
			:id="'input-' + fieldKey"
			class="number-input-field"
			:value="value ?? ''"
			:label="field.name"
			type="number"
			:placeholder="t('assistant','Type some number')"
			@update:value="onUpdateValue" />
		<NcButton type="tertiary"
			:title="field.description">
			<template #icon>
				<HelpCircleIcon />
			</template>
		</NcButton>
	</div>
</template>

<script>
import HelpCircleIcon from 'vue-material-design-icons/HelpCircle.vue'

import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'NumberField',

	components: {
		NcInputField,
		NcButton,
		HelpCircleIcon,
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
		width: 200px;
	}
}
</style>
