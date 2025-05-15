<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="task-type-fields">
		<TaskTypeField v-for="(field, key) in shape"
			:key="'shape' + key"
			:field-key="key"
			:field="field"
			:value="values[key] ?? null"
			:options="getInputFieldOptions(field, key)"
			:is-output="isOutput"
			:defaults="defaults"
			@submit="onSubmit"
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
				:options="getOptionalInputFieldOptions(field, key)"
				:is-output="isOutput"
				:defaults="optionalDefaults"
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
		shapeOptions: {
			type: [Object, Array, null],
			default: null,
		},
		optionalShapeOptions: {
			type: [Object, Array, null],
			default: null,
		},
		defaults: {
			type: [Object, Array, null],
			default: null,
		},
		optionalDefaults: {
			type: [Object, Array, null],
			default: null,
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
		'submit',
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
		getInputFieldOptions(field, key) {
			if (field.type === 'Enum'
				&& this.shapeOptions !== null
				&& !Array.isArray(this.shapeOptions)
				&& this.shapeOptions[key]
			) {
				return this.shapeOptions[key]
			}
			return undefined
		},
		getOptionalInputFieldOptions(field, key) {
			if (field.type === 'Enum'
				&& this.optionalShapeOptions !== null
				&& !Array.isArray(this.optionalShapeOptionsshapeOptions)
				&& this.optionalShapeOptions[key]
			) {
				return this.optionalShapeOptions[key]
			}
			return undefined
		},
		onSubmit(event) {
			console.debug('[assistant] field value submitted', event)
			this.$emit('submit', event)
		},
		onValueChange(key, value) {
			const newValues = {
				...this.values,
				[key]: value,
			}
			console.debug('[assistant] field value change', newValues)
			this.$emit('update:values', newValues)
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
