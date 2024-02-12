<template>
	<ul class="task-list">
		<TaskListItem v-for="task in tasks"
			:key="task.id"
			class="task-list--item"
			:task="task" />
	</ul>
</template>

<script>
import TaskListItem from './TaskListItem.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'TaskList',

	components: {
		TaskListItem,
	},

	props: {
		taskType: {
			type: [String, null],
			default: null,
		},
	},

	emits: [
		'load-task',
	],

	data() {
		return {
			tasks: [],
		}
	},

	computed: {
	},

	watch: {
		taskType() {
			this.getTasks()
		},
	},

	mounted() {
		this.getTasks()
	},

	methods: {
		getTasks() {
			this.loading = true
			const req = {
				params: {
					taskType: this.taskType,
				},
			}
			const url = generateUrl('/apps/assistant/tasks')
			axios.get(url, req).then(response => {
				this.tasks = response.data.tasks
				console.debug('aaaaa tasks', response.data)
			}).catch(error => {
				console.error(error)
			}).then(() => {
				this.loading = false
			})
		},
	},
}
</script>

<style lang="scss">
.task-list {
	//display: flex;
	//flex-direction: column;
	//align-items: center;
	//row-gap: 8px;
	//column-gap: 6px;

	&--item {
		//margin: 0 12px 0 12px;
		width: 99% !important;
	}
}
</style>
