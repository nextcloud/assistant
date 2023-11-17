<template>
	<NcEmptyContent
		:title="t('assistant', 'Getting results…')"
		:name="t('assistant', 'Getting results…')"
		:description="description">
		<template #action>
			<NcButton
				@click="$emit('cancel')">
				{{ t('assistant', 'Run in the background') }}
			</NcButton>
		</template>
		<template #icon>
			<NcLoadingIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'RunningEmptyContent',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
	},

	props: {
		description: {
			type: String,
			required: true,
		},
	},

	emits: [
		'cancel',
	],

	data() {
		return {
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style lang="scss">
// nothing yet
</style>
