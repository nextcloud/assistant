<template>
	<NcEmptyContent
		:title="title"
		:name="title"
		:description="t('assistant', 'You will receive a notification when it has finished')">
		<template v-if="showCloseButton" #action>
			<NcButton
				type="tertiary"
				@click="$emit('close')">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('assistant', 'Close') }}
			</NcButton>
		</template>
		<template #icon>
			<AssistantIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

import AssistantIcon from './icons/AssistantIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'ScheduledEmptyContent',

	components: {
		AssistantIcon,
		NcButton,
		NcEmptyContent,
		CloseIcon,
	},

	props: {
		description: {
			type: String,
			required: true,
		},
		showCloseButton: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'cancel',
	],

	data() {
		return {
			title: t('assistant', 'Your task has been scheduled'),
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
