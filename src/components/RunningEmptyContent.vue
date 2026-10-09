<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcEmptyContent
		:name="progressMessage"
		:description="description">
		<template #action>
			<div class="running-actions">
				<div v-if="speculativeProgress !== null"
					class="progress">
					<span>{{ formattedProgress }} %</span>
					<NcProgressBar
						:value="speculativeProgress" />
				</div>
				<div class="inline">
					<span v-if="formattedRuntime">
						{{ formattedRuntime }}
					</span>
					<span v-if="formattedPosition">
						{{ formattedPosition }}
					</span>
				</div>
				<div class="info-text-block">
					{{ t('assistant', 'This task is running in the background.') }}
					<br>
					{{ t('assistant', 'You can safely close the assistant or browse other tasks.') }}
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
				<NcNoteCard v-if="taskStatus === TASK_STATUS_STRING.scheduled && tooLongForScheduling" show-alert type="warning">
					{{ t('assistant', 'This task is taking longer to start running than expected. Please contact your administrator to ensure that Assistant is correctly configured.') }}
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

/**
 * Convert a progress fraction (0..1) into a percentage, capped below 100
 * because a task that reached 100 % is finished and shows no progress anymore.
 * Returns null for values that cannot be displayed (null, NaN, Infinity).
 *
 * @param {number|null|undefined} fraction the progress reported by the backend
 * @return {number|null} the percentage or null
 */
function toProgressPercent(fraction) {
	if (!Number.isFinite(fraction)) {
		return null
	}
	return Math.min(Math.max(fraction, 0), 0.9999) * 100
}

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
		/** Progress reported by the backend, a fraction between 0 and 1 */
		progress: {
			type: [Number, null],
			default: null,
		},
		taskPosition: {
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
		startedAt: {
			type: [Number, null],
			default: null,
		},
		completionExpectedAt: {
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
			now: Date.now() / 1000,
			timer: null,
			speculativeProgress: toProgressPercent(this.progress),
		}
	},

	computed: {
		tooLongForScheduling() {
			return this.scheduledAt !== null && (this.scheduledAt + (60 * 5)) < this.now
		},
		TASK_STATUS_STRING() {
			return TASK_STATUS_STRING
		},
		progressPercent() {
			return toProgressPercent(this.progress)
		},
		formattedProgress() {
			if (this.speculativeProgress !== null) {
				return this.speculativeProgress.toFixed(2)
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
		formattedPosition() {
			if (this.taskPosition === null || this.taskStatus !== TASK_STATUS_STRING.scheduled) {
				return ''
			}
			return t('assistant', 'Task position: {position}', { position: this.taskPosition })
		},
		progressMessage() {
			if (this.taskStatus === TASK_STATUS_STRING.scheduled || this.taskStatus === null) {
				return t('assistant', 'Waiting…')
			}
			return t('assistant', 'Processing…')
		},
	},

	watch: {
		progressPercent(percent) {
			// Progress reported by the backend takes precedence over the local estimate
			this.speculativeProgress = percent
		},
	},

	mounted() {
		// Need to use a timer to update the state for now otherwise the component won't re-render when the time changes
		this.timer = setInterval(() => {
			console.debug('scheduledAt', this.scheduledAt)
			console.debug('status', this.taskStatus)
			this.now = Date.now() / 1000
			this.updateProgressSpeculatively()
		}, 2000)
	},

	beforeUnmount() {
		if (this.timer) {
			clearInterval(this.timer)
		}
	},

	methods: {
		updateProgressSpeculatively() {
			if (this.progressPercent === null || !Number.isFinite(this.startedAt) || !Number.isFinite(this.completionExpectedAt)) {
				return
			}
			const total = this.completionExpectedAt - this.startedAt
			const elapsed = this.now - this.startedAt
			// The expected completion time can degenerate to the task start time or
			// even to before it after a long queue wait, so guard the division here
			if (total <= 0 || elapsed <= 0) {
				return
			}
			const newProgress = toProgressPercent(elapsed / total)
			if (newProgress > this.speculativeProgress) {
				this.speculativeProgress = newProgress
			}
		},
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

	.info-text-block {
		text-align: center;
	}

	.inline {
		display: flex;
		gap: 4px;
	}
}
</style>
