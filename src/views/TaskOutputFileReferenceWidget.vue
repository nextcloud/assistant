<template>
	<div class="task-output-widget">
		<MediaField :field="field"
			field-key="dummyKey"
			:value="richObject.fileId"
			:is-output="true" />
	</div>
</template>

<script>

import MediaField from '../components/fields/MediaField.vue'

import { SHAPE_TYPE_NAMES } from '../constants.js'

export default {
	name: 'TaskOutputFileReferenceWidget',

	components: {
		MediaField,
	},

	provide() {
		return {
			providedCurrentTaskId: () => this.richObject.taskId,
		}
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			field: {
				description: 'field desc',
				name: t('assistant', 'Output file for task {taskId} ({taskTypeName})', { taskId: this.richObject.taskId, taskTypeName: this.richObject.taskTypeName }),
				type: SHAPE_TYPE_NAMES.File,
			},
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.task-output-widget {
	margin: 4px;
}
</style>
