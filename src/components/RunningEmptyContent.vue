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
	},

	emits: [
		'cancel',
		'background-notify',
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
