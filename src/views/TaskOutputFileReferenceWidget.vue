<template>
	<div class="task-output-widget">
		<strong>
			{{ t('assistant', 'Output file for task {taskId} ({taskTypeName})', { taskId: richObject.taskId, taskTypeName: richObject.taskTypeName }) }}
		</strong>
	</div>
</template>

<script>

export default {
	name: 'TaskOutputFileReferenceWidget',

	components: {
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
