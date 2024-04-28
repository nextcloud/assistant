<template>
	<div class="task-type-fields">
		<TaskTypeField v-for="(field, key) in shape"
			:key="'shape' + key"
			:field-key="key"
			:field="field"
			:value="values[key] ?? null"
			:is-output="isOutput"
			@update:value="onValueChange(key, $event)" />
		<NcButton v-if="hasOptionalShape"
			@click="advanced = !advanced">
			<template #icon>
				<ChevronDownIcon v-if="advanced" />
				<ChevronRightIcon v-else />
			</template>
			{{ t('assistant', 'Advanced') }}
		</NcButton>
		<div v-if="advanced"
			class="advanced">
			<TaskTypeField v-for="(field, key) in myOptionalShape"
				:key="'shape' + key"
				:field-key="key"
				:field="field"
				:value="values[key] ?? null"
				:is-output="isOutput"
				@update:value="onValueChange(key, $event)" />
		</div>
	</div>
</template>

<script>
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import TaskTypeField from './TaskTypeField.vue'

export default {
	name: 'TaskTypeFields',

	components: {
		NcButton,
		ChevronRightIcon,
		ChevronDownIcon,
		TaskTypeField,
	},

	props: {
		values: {
			type: [Object, null],
			default: null,
		},
		shape: {
			type: Object,
			required: true,
		},
		optionalShape: {
			type: [Object, Array, null],
			default: () => {},
		},
		isOutput: {
			type: Boolean,
			required: true,
		},
	},

	emits: [
		'update:values',
	],

	data() {
		return {
			advanced: false,
		}
	},

	computed: {
		hasOptionalShape() {
			return this.optionalShape !== null
				&& !Array.isArray(this.optionalShape)
				&& Object.keys(this.optionalShape).length > 0
		},
		myOptionalShape() {
			if (this.optionalShape === null || Array.isArray(this.optionalShape)) {
				return {}
			}
			return this.optionalShape
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onValueChange(key, value) {
			this.$emit('update:values', {
				...this.values,
				[key]: value,
			})
		},
	},
}
</script>

<style lang="scss">
.task-type-fields {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 8px;

	.advanced {
		width: 100%;
	}
}
</style>
