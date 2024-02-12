<template>
	<NcListItem
		class="task-list-item"
		:name="name"
		:bold="false"
		:active="false"
		:details="details"
		:counter-number="counter">
		<!--template #icon>
			<NcAvatar disable-menu :size="44" user="janedoe" display-name="Jane Doe" />
		</template-->
		<template #subname>
			{{ subName }}
		</template>
		<!--template #indicator>
			<CheckboxBlankCircle :size="16" fill-color="#fff" />
		</template-->
		<template #actions>
			<NcActionButton @click="cancelTask">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('assistant', 'Cancel') }}
			</NcActionButton>
			<NcActionButton @click="deleteTask">
				<template #icon>
					<DeleteIcon />
				</template>
				{{ t('assistant', 'Delete') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import { STATUS } from '../constants.js'

export default {
	name: 'TaskListItem',

	components: {
		NcListItem,
		NcActionButton,
		CloseIcon,
		DeleteIcon,
	},

	props: {
		task: {
			type: Object,
			required: true,
		},
	},

	emits: [
		'delete',
	],

	data() {
		return {
		}
	},

	computed: {
		name() {
			if (this.task.taskType === 'copywriter') {
				return this.task.inputs.sourceMaterial
			} else if (this.task.taskType === 'speech-to-text') {
				return t('assistant', 'Audio input')
			}
			return this.task.inputs.prompt ?? t('assistant', 'Unknown input')
		},
		subName() {
			if (this.task.status === STATUS.successfull) {
				if (this.task.taskType === 'OCP\\TextToImage\\Task') {
					return n('assistant', '{n} image has been generated', '{n} images have been generated', this.task.inputs.nResults, { n: this.task.inputs.nResults })
				}
				return t('assistant', 'Output') + ': ' + this.task.output
			}
			return '??'
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
:deep(.task-list-item) {
	.list-item {
		width: 99% !important;
	}
}
</style>
