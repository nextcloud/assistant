<template>
	<div>
		<ul
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
		<NcEmptyContent v-if="!loading && tasks.length === 0"
			:name="t('assistant', 'Nothing yet')"
			:description="emptyContentDescription">
			<template #icon>
				<HistoryIcon />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import HistoryIcon from 'vue-material-design-icons/History.vue'

import TaskListItem from './TaskListItem.vue'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { STATUS } from '../constants.js'

export default {
	name: 'TaskList',

	components: {
		HistoryIcon,
		TaskListItem,
		NcEmptyContent,
	},

	props: {
		taskType: {
			type: [Object, null],
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'load-task',
		'try-again',
	],

	data() {
		return {
			tasks: [],
		}
	},

	computed: {
		emptyContentDescription() {
			return t('assistant', 'You have not submitted any "{taskTypeName}" task yet', { taskTypeName: this.taskType?.name })
		},
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
			this.$emit('update:loading', true)
			const req = {
				params: {
					taskType: this.taskType.id,
				},
			}
			const url = generateOcsUrl('/apps/assistant/api/v1/tasks')
			axios.get(url, req).then(response => {
				this.tasks = response.data?.ocs?.data?.tasks
			}).catch(error => {
				console.error(error)
			}).then(() => {
				this.$emit('update:loading', false)
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
	&--item {
		width: 99% !important;
	}
}
</style>
