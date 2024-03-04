<template>
	<NcListItem
		class="task-list-item"
		:name="name"
		:title="subName"
		:bold="false"
		:active="false"
		:details="details"
		@click="$emit('load')">
		<template #icon>
			<component :is="icon"
				style="margin-right: 8px;"
				:title="statusTitle" />
		</template>
		<template #subname>
			<Text2ImageInlineDisplay v-if="isSuccessful && isText2Image"
				:image-gen-id="task.output" />
			<span v-else>
				{{ subName }}
			</span>
		</template>
		<!--template #indicator>
			<CheckboxBlankCircle :size="16" fill-color="#fff" />
		</template-->
		<template #actions>
			<NcActionButton v-if="isSuccessful"
				:close-after-click="true"
				@click="onCopyOutput">
				<template #icon>
					<ContentCopyIcon />
				</template>
				{{ t('assistant', 'Copy result') }}
			</NcActionButton>
			<NcActionButton @click="$emit('try-again')">
				<template #icon>
					<RedoIcon />
				</template>
				{{ t('assistant', 'Try again') }}
			</NcActionButton>
			<NcActionButton v-if="isScheduled"
				@click="$emit('cancel')">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('assistant', 'Cancel') }}
			</NcActionButton>
			<NcActionButton @click="$emit('delete')">
				<template #icon>
					<DeleteIcon />
				</template>
				{{ t('assistant', 'Delete') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import RedoIcon from 'vue-material-design-icons/Redo.vue'
import ProgressQuestionIcon from 'vue-material-design-icons/ProgressQuestion.vue'
import ProgressCheckIcon from 'vue-material-design-icons/ProgressCheck.vue'
import ProgressClockIcon from 'vue-material-design-icons/ProgressClock.vue'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'

import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import moment from '@nextcloud/moment'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import VueClipboard from 'vue-clipboard2'
import Vue from 'vue'

import { STATUS } from '../constants.js'
import Text2ImageInlineDisplay from './Text2Image/Text2ImageInlineDisplay.vue'

Vue.use(VueClipboard)

export default {
	name: 'TaskListItem',

	components: {
		Text2ImageInlineDisplay,
		NcListItem,
		NcActionButton,
		CloseIcon,
		DeleteIcon,
		ProgressClockIcon,
		ProgressCheckIcon,
		ProgressQuestionIcon,
		CheckIcon,
		AlertCircleOutlineIcon,
		RedoIcon,
		ContentCopyIcon,
	},

	props: {
		task: {
			type: Object,
			required: true,
		},
	},

	emits: [
		'delete',
		'cancel',
		'try-again',
		'load',
	],

	data() {
		return {
			copied: false,
		}
	},

	computed: {
		isScheduled() {
			return this.task.status === STATUS.scheduled
		},
		isSuccessful() {
			return this.task.status === STATUS.successfull
		},
		isText2Image() {
			return this.task.taskType === 'OCP\\TextToImage\\Task'
		},
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
				return t('assistant', 'Result') + ': ' + this.task.output
			} else if (this.task.status === STATUS.scheduled) {
				if (this.task.taskType === 'OCP\\TextToImage\\Task') {
					return n('assistant', 'Generation of {n} image is scheduled', 'Generation of {n} images is scheduled', this.task.inputs.nResults, { n: this.task.inputs.nResults })
				}
				return t('assistant', 'This task is scheduled')
			} else if (this.task.status === STATUS.running) {
				return t('assistant', 'Running...')
			} else if (this.task.status === STATUS.failed) {
				return t('assistant', 'Failed') + ': ' + (this.task.output ?? t('assistant', 'Unknown error'))
			}
			return t('assistant', 'Unknown status')
		},
		details() {
			return moment.unix(this.task.timestamp).fromNow()
		},
		icon() {
			if (this.task.status === STATUS.successfull) {
				return CheckIcon
			} else if (this.task.status === STATUS.failed) {
				return AlertCircleOutlineIcon
			} else if (this.task.status === STATUS.running) {
				return ProgressCheckIcon
			} else if (this.task.status === STATUS.scheduled) {
				return ProgressClockIcon
			}
			return ProgressQuestionIcon
		},
		statusTitle() {
			if (this.task.status === STATUS.successfull) {
				return t('assistant', 'Succeeded')
			} else if (this.task.status === STATUS.failed) {
				return t('assistant', 'Failed')
			} else if (this.task.status === STATUS.running) {
				return t('assistant', 'Running')
			} else if (this.task.status === STATUS.scheduled) {
				return t('assistant', 'Scheduled')
			}
			return t('assistant', 'Unknown status')
		},
		formattedOutput() {
			if (!this.isSuccessful) {
				return null
			}
			if (this.task.taskType === 'OCP\\TextToImage\\Task') {
				return window.location.protocol + '//' + window.location.host + generateUrl('/apps/assistant/i/{imageGenId}', { imageGenId: this.task.output })
			}
			return this.task.output.trim()
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		async onCopyOutput() {
			try {
				await this.$copyText(this.formattedOutput)
				this.copied = true
				setTimeout(() => {
					this.copied = false
				}, 5000)
				showSuccess(t('assistant', 'Task result was copied to clipboard'))
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Result could not be copied to clipboard'))
			}
		},
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
