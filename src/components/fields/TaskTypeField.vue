<template>
	<component :is="component"
		class="field"
		:field-key="fieldKey"
		:value="value"
		:field="field"
		:is-output="isOutput"
		@update:value="$emit('update:value', $event)" />
</template>

<script>
import TextField from './TextField.vue'
import NumberField from './NumberField.vue'
import MediaField from './MediaField.vue'
import ListOfMediaField from './ListOfMediaField.vue'

import { SHAPE_TYPE_NAMES } from '../../constants.js'

export default {
	name: 'TaskTypeField',

	components: {
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
		isOutput: {
			type: Boolean,
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
		isListOfMedia() {
			return [
				SHAPE_TYPE_NAMES.ListOfAudios,
				SHAPE_TYPE_NAMES.ListOfImages,
				SHAPE_TYPE_NAMES.ListOfVideos,
				SHAPE_TYPE_NAMES.ListOfFiles,
			].includes(this.field.type)
		},
		isMedia() {
			return [
				SHAPE_TYPE_NAMES.Audio,
				SHAPE_TYPE_NAMES.Image,
				SHAPE_TYPE_NAMES.Video,
				SHAPE_TYPE_NAMES.File,
			].includes(this.field.type)
		},
		component() {
			if (this.field.type === SHAPE_TYPE_NAMES.Text) {
				return TextField
			} else if (this.field.type === SHAPE_TYPE_NAMES.Number) {
				return NumberField
			} else if (this.isMedia) {
				return MediaField
			} else if (this.isListOfMedia) {
				return ListOfMediaField
			}
			return TextField
		},
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
.field {
	width: 100%;
}
</style>
