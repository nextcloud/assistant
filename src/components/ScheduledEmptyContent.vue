<template>
	<NcEmptyContent
		:name="title"
		:description="t('assistant', 'You will receive a notification when it has finished')">
		<template #action>
			<div class="schedule-confirmation-actions">
				<NcButton
					@click="$emit('back')">
					<template #icon>
						<ArrowLeftIcon />
					</template>
					{{ t('assistant', 'Back to the Assistant') }}
				</NcButton>
				<NcButton v-if="showCloseButton"
					@click="$emit('close')">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('assistant', 'Close') }}
				</NcButton>
			</div>
		</template>
		<template #icon>
			<AssistantIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'

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
		ArrowLeftIcon,
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
		'back',
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
.schedule-confirmation-actions {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
}
</style>
