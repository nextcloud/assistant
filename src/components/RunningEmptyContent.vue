<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcEmptyContent
		:name="t('assistant', 'Getting results…')"
		:description="description">
		<template #action>
			<div class="running-actions">
				<div v-if="progress !== null"
					class="progress">
					<span>{{ formattedProgress }} %</span>
					<NcProgressBar
						:value="progress" />
				</div>
				<div v-if="formattedRuntime">
					{{ formattedRuntime }}
				</div>
				<NcButton
					@click="$emit('background-notify', !isNotifyEnabled)">
					<template #icon>
						<BellRingOutlineIcon v-if="isNotifyEnabled" />
						<BellOutlineIcon v-else />
					</template>
					{{ t('assistant', 'Get notified when the task finishes') }}
				</NcButton>
				<NcButton
					@click="$emit('cancel')">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('assistant', 'Cancel task') }}
				</NcButton>
				<NcNoteCard v-if="taskStatus === TASK_STATUS_STRING.scheduled && toLongForScheduling" show-alert type="warning">
					{{ t('assistant', 'This task is taking longer to start running than expected. Please contact your administrator to ensure this task type is being picked up.') }}
				</NcNoteCard>
			</div>
		</template>
		<template #icon>
			<NcLoadingIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
import BellRingOutlineIcon from 'vue-material-design-icons/BellRingOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { TASK_STATUS_STRING } from '../constants.js'

export default {
	name: 'RunningEmptyContent',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
		BellOutlineIcon,
		BellRingOutlineIcon,
		CloseIcon,
		NcNoteCard,
	},

	props: {
		description: {
			type: String,
			required: true,
		},
		progress: {
			type: [Number, null],
			default: null,
		},
		expectedRuntime: {
			type: [Number, null],
			default: null,
		},
		isNotifyEnabled: {
			type: Boolean,
			default: false,
		},
		taskStatus: {
			type: [String, null],
			default: null,
		},
		scheduledAt: {
			type: [Number, null],
			default: null,
		},
	},

	emits: [
		'cancel',
		'background-notify',
	],

	data() {
		return {
			toLongForScheduling: false,
			timer: null,
		}
	},

	computed: {
		TASK_STATUS_STRING() {
			return TASK_STATUS_STRING
		},
		formattedProgress() {
			if (this.progress !== null) {
				return this.progress.toFixed(2)
			}
			return null
		},
		formattedRuntime() {
			if (this.expectedRuntime === null) {
				return ''
			}
			if (this.expectedRuntime < 60) {
				return t('assistant', 'This may take a few seconds…')
			}
			return t('assistant', 'This may take a few minutes…')
		},
	},

	mounted() {
		this.timer = setInterval(() => {
			if (this.scheduledAt === null) {
				this.toLongForScheduling = false
				return
			}
			this.toLongForScheduling = this.scheduledAt + 60 * 5 < Date.now() / 1000
		}, 2000)
	},

	beforeUnmount() {
		if (this.timer) {
			clearInterval(this.timer)
		}
	},

	methods: {
	},
}
</script>

<style lang="scss">
.running-actions {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;

	.progress {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 2px;
	}
}
</style>
