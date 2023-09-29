<template>
	<NcEmptyContent
		:title="t('assistant', 'Your task is running')"
		:name="t('assistant', 'Your task is running')"
		:description="t('assistant', 'If it takes too long...')">
		<template #action>
			<NcButton
				@click="$emit('cancel')">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('assistant', 'Run in the background') }}
			</NcButton>
		</template>
		<template #icon>
			<NcLoadingIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'RunningEmptyContent',

	components: {
		NcButton,
		NcEmptyContent,
		CloseIcon,
		NcLoadingIcon,
	},

	props: {
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
