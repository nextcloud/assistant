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
				<NcButton
					@click="$emit('background-notify')">
					<template #icon>
						<ProgressClockIcon />
					</template>
					{{ t('assistant', 'Run task in the background and get notified') }}
				</NcButton>
				<NcButton
					@click="$emit('back')">
					<template #icon>
						<ArrowLeftIcon />
					</template>
					{{ t('assistant', 'Back to the Assistant') }}
				</NcButton>
				<NcButton
					@click="$emit('cancel')">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('assistant', 'Cancel task') }}
				</NcButton>
			</div>
		</template>
		<template #icon>
			<NcLoadingIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ProgressClockIcon from 'vue-material-design-icons/ProgressClock.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'RunningEmptyContent',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
		ArrowLeftIcon,
		CloseIcon,
		ProgressClockIcon,
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
	},

	emits: [
		'cancel',
		'background-notify',
		'back',
	],

	data() {
		return {
		}
	},

	computed: {
		formattedProgress() {
			if (this.progress !== null) {
				return this.progress.toFixed(2)
			}
			return null
		},
	},

	mounted() {
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
	}
}
</style>
