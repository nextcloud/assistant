<template>
	<NcEmptyContent
		:name="t('assistant', 'Getting results…')"
		:description="description">
		<template #action>
			<div class="actions">
				<div v-if="progress !== null"
					class="progress">
					<span>{{ formattedProgress }} %</span>
					<NcProgressBar
						:value="progress" />
				</div>
				<NcButton
					@click="$emit('background-notify')">
					{{ t('assistant', 'Run in the background and get notified') }}
				</NcButton>
				<NcButton
					@click="$emit('cancel')">
					{{ t('assistant', 'Cancel') }}
				</NcButton>
			</div>
		</template>
		<template #icon>
			<NcLoadingIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
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
.actions {
	display: flex;
	flex-direction: column;
	gap: 12px;

	.progress {
		display: flex;
		flex-direction: column;
		align-items: center;
	}
}
</style>
