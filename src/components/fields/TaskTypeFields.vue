<template>
	<div class="task-type-fields">
		<TaskTypeField v-for="(field, key) in shape"
			:key="'shape' + key"
			:field-key="key"
			:field="field"
			:value="values[key] ?? null"
			:is-output="isOutput"
			@update:value="onValueChange(key, $event)" />
		<!--NcButton v-if="hasOptionalShape"
			@click="$emit('update:show-advanced', !showAdvanced)">
			<template #icon>
				<ChevronDownIcon v-if="showAdvanced" />
				<ChevronRightIcon v-else />
			</template>
			{{ t('assistant', 'Advanced') }}
		</NcButton-->
		<div v-if="showAdvanced"
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
import TaskTypeField from './TaskTypeField.vue'

export default {
	name: 'TaskTypeFields',

	components: {
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
		showAdvanced: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:values',
	],

	data() {
		return {
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
