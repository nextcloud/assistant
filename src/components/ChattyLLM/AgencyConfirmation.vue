<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcNoteCard type="info"
		class="agency-confirmation">
		<div class="notecard-content">
			<span>
				{{ t('assistant', 'The Assistant wants to perform sensitive actions on your behalf.') }}
			</span>
			<AgencyActions :actions="actions" />
			<div class="footer">
				<NcButton variant="tertiary"
					:title="radiusInfo.description"
					:text="radiusInfo.label"
					class="help radius">
					<template #icon>
						<component :is="radiusInfo.icon" :size="20" />
					</template>
				</NcButton>
				<NcButton variant="tertiary"
					:title="hint"
					class="help">
					<template #icon>
						<InformationOutlineIcon :size="20" />
					</template>
				</NcButton>
				<NcButton variant="secondary"
					@click="$emit('reject')">
					{{ t('assistant', 'Cancel') }}
					<template #icon>
						<CloseIcon :size="20" />
					</template>
				</NcButton>
				<NcButton variant="primary"
					@click="$emit('confirm')">
					{{ t('assistant', 'Confirm those actions') }}
					<template #icon>
						<AssistantIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</NcNoteCard>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import AccountOutlineIcon from 'vue-material-design-icons/AccountOutline.vue'
import AccountMultipleOutlineIcon from 'vue-material-design-icons/AccountMultipleOutline.vue'
import AccountGroupOutlineIcon from 'vue-material-design-icons/AccountGroupOutline.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import AssistantIcon from '../icons/AssistantIcon.vue'

import AgencyActions from './AgencyActions.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'

const radii = {
	self: {
		icon: AccountOutlineIcon,
		label: t('assistant', 'Self'),
		description: t('assistant', 'These actions only affect you.'),
	},
	individuals: {
		icon: AccountMultipleOutlineIcon,
		label: t('assistant', 'Individuals'),
		description: t('assistant', 'These actions affect specific other people.'),
	},
	group: {
		icon: AccountGroupOutlineIcon,
		label: t('assistant', 'Group'),
		description: t('assistant', 'These actions affect a group or team of people.'),
	},
	external: {
		icon: EarthIcon,
		label: t('assistant', 'External'),
		description: t('assistant', 'These actions affect people or services outside of this Nextcloud instance.'),
	},
}

const radiusOrder = Object.keys(radii)

export default {
	name: 'AgencyConfirmation',

	components: {
		AgencyActions,
		AssistantIcon,
		NcNoteCard,
		NcButton,
		CloseIcon,
		AccountOutlineIcon,
		AccountMultipleOutlineIcon,
		AccountGroupOutlineIcon,
		EarthIcon,
		InformationOutlineIcon,
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

	data: () => {
		return {
			hint: t('assistant', 'If you are not satisfied with the actions the Assistant wants to run, you can adjust your request by sending a new message instead of clicking the "Cancel" button.'),
		}
	},

	computed: {
		radiusInfo() {
			// show the largest radius among all actions
			// for actions with an undefined or unknown radius, assume they are EXTERNAL
			const largest = this.actions.reduce((max, action) => {
				const index = radiusOrder.indexOf(action?.radius)
				return Math.max(max, index === -1 ? radiusOrder.length - 1 : index)
			}, 0)
			return radii[radiusOrder[largest]]
		},
	},
}
</script>

<style lang="scss">
.agency-confirmation > div {
	width: 100%;
}
</style>

<style lang="scss" scoped>
.notecard-content {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 8px;

	.footer {
		width: 100%;
		display: flex;
		gap: 4px;
		align-items: center;
		justify-content: end;

		.radius {
			margin-right: auto;
		}
	}
}
</style>
