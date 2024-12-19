<template>
	<NcNoteCard type="warning">
		<span>
			{{ t('assistant', 'The assistant wants to perform sensitive actions on your behalf:') }}
		</span>
		TODO: List the formatted actions
		<div class="footer">
			<NcButton type="success"
				@click="$emit('confirm')">
				{{ t('assistant', 'Confirm those actions') }}
				<template #icon>
					<AssistantIcon />
				</template>
			</NcButton>
			<NcButton type="error"
				@click="$emit('reject')">
				{{ t('assistant', 'Cancel') }}
				<template #icon>
					<CloseIcon />
				</template>
			</NcButton>
		</div>
	</NcNoteCard>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import AssistantIcon from '../icons/AssistantIcon.vue'

import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'AgencyConfirmation',

	components: {
		AssistantIcon,
		NcNoteCard,
		NcButton,
		CloseIcon,
	},

	props: {
		actions: {
			type: Array,
			required: true,
		},
	},

	emits: [
		'confirm',
		'reject',
	],
}
</script>

<style lang="scss" scoped>
.footer {
	width: 100%;
	display: flex;
	gap: 4px;
	align-items: center;
	justify-content: end;
}
</style>
