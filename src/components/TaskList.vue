<template>
	<NcLoadingIcon v-if="loading"
		:size="64" />
	<ul v-else
		class="task-list">
		<TaskListItem v-for="task in tasks"
			:key="task.id"
			class="task-list--item"
			:task="task"
			@try-again="$emit('try-again', task)"
			@load="$emit('load-task', task)"
			@delete="onTaskDelete(task)"
			@cancel="onTaskCancel(task)" />
	</ul>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import TaskListItem from './TaskListItem.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { STATUS } from '../constants.js'

export default {
	name: 'TaskList',

	components: {
		TaskListItem,
		NcLoadingIcon,
	},

	props: {
		taskType: {
			type: [String, null],
			default: null,
		},
	},

	emits: [
		'load-task',
		'try-again',
	],

	data() {
		return {
			loading: false,
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
			const url = generateOcsUrl('/apps/assistant/api/v1/tasks')
			axios.get(url, req).then(response => {
				this.tasks = response.data?.ocs?.data?.tasks
			}).catch(error => {
				console.error(error)
			}).then(() => {
				this.loading = false
			})
		},
		onTaskDelete(task) {
			const url = generateOcsUrl('/apps/assistant/api/v1/task/{id}', { id: task.id })
			axios.delete(url).then(response => {
				const index = this.tasks.findIndex(t => { return t.id === task.id })
				if (index !== -1) {
					this.tasks.splice(index, 1)
				}
			}).catch(error => {
				console.error(error)
			})
		},
		onTaskCancel(task) {
			const url = generateOcsUrl('/apps/assistant/api/v1/task/cancel/{id}', { id: task.id })
			axios.put(url).then(response => {
				task.status = STATUS.failed
				task.output = t('assistant', 'Canceled by user')
			}).catch(error => {
				console.error(error)
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
