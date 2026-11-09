<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcNoteCard :type="(destructive.destructive || irreversible.irreversible) ? 'warning' : 'info'"
		class="agency-confirmation">
		<div class="notecard-content">
			<span>
				{{ t('assistant', 'The Assistant wants to perform sensitive actions on your behalf.') }}
			</span>
			<AgencyActions :actions="actions" />
			<div class="footer">
				<div class="footer-left">
					<NcButton v-if="radiusInfo"
						variant="tertiary"
						:title="radiusTooltip"
						:text="radiusInfo.label"
						class="help">
						<template #icon>
							<component :is="radiusInfo.icon" :size="20" />
						</template>
					</NcButton>
					<NcButton v-if="itemCount.itemCount > 1"
						variant="tertiary"
						:title="itemCount.description"
						:text="String(itemCount.itemCount)"
						class="help">
						<template #icon>
							<TallyMark5Icon :size="20" />
						</template>
					</NcButton>
				</div>
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
import TallyMark5Icon from 'vue-material-design-icons/TallyMark5.vue'
import AssistantIcon from '../icons/AssistantIcon.vue'

import AgencyActions from './AgencyActions.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'

const radii = {
	self: {
		icon: AccountOutlineIcon,
		// TRANSLATORS Label for AI agent actions whose "impulse radius", or action scope, affects only oneself
		label: t('assistant', 'Self'),
		// TRANSLATORS Description for AI agent actions whose "impulse radius", or action scope, affects only oneself
		description: t('assistant', 'These actions only affect you.'),
	},
	individuals: {
		icon: AccountMultipleOutlineIcon,
		// TRANSLATORS Label for AI agent actions whose "impulse radius", or action scope, affects specific individuals
		label: t('assistant', 'Individuals'),
		// TRANSLATORS Description for AI agent actions whose "impulse radius", or action scope, affects specific individuals
		description: t('assistant', 'These actions affect specific other people.'),
	},
	group: {
		icon: AccountGroupOutlineIcon,
		// TRANSLATORS Label for AI agent actions whose "impulse radius", or action scope, affects groups of users
		label: t('assistant', 'Group'),
		// TRANSLATORS Description for AI agent actions whose "impulse radius", or action scope, affects groups of users
		description: t('assistant', 'These actions affect a group or team of people.'),
	},
	external: {
		icon: EarthIcon,
		// TRANSLATORS Label for AI agent actions whose "impulse radius", or action scope, extends beyond Nextcloud
		label: t('assistant', 'External'),
		// TRANSLATORS Description for AI agent actions whose "impulse radius", or action scope, extends beyond Nextcloud
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
		TallyMark5Icon,
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
		destructive() {
			// determine if any of the pending actions are destructive
			// actions that don't report a destructive state (older backends) are assumed to be non-destructive
			for (const action of this.actions) {
				if (action?.destructive !== undefined && action?.destructive !== null && action.destructive !== false) {
					return {
						destructive: true,
						// TRANSLATORS Additional description for destructive AI agent actions
						description: t('assistant', 'Some content may be lost.'),
					}
				}
			}
			return {
				destructive: false,
				description: '',
			}
		},

		irreversible() {
			// determine if any of the pending actions are irreversible
			// actions that don't report their reversibility (older backends) are assumed to be reversible
			for (const action of this.actions) {
				if (action?.reversibility === 'irreversible') {
					return {
						irreversible: true,
						// TRANSLATORS Additional description for irreversible AI agent actions
						description: t('assistant', 'Some actions cannot be reversed.'),
					}
				}
			}
			return {
				irreversible: false,
				description: '',
			}
		},

		itemCount() {
			// determine how many items are affected by the pending actions
			let itemCount = 0
			for (const action of this.actions) {
				if (Number.isFinite(action?.item_count)) {
					itemCount += action.item_count
				}
			}
			return {
				itemCount,
				// TRANSLATORS Additional description for pending AI agent actions to be confirmed by the user
				description: n('assistant', '%n item is affected by these actions.', '%n items are affected by these actions.', itemCount),
			}
		},

		radiusInfo() {
			// show the largest radius among all actions
			// actions that don't report a radius (older backends) are ignored
			// actions with an unknown radius value are assumed to be external
			// returns null if no action reports a radius
			const largest = this.actions.reduce((max, action) => {
				if (action?.impulse_radius === undefined || action?.impulse_radius === null) {
					return max
				}
				const index = radiusOrder.indexOf(action.impulse_radius)
				return Math.max(max, index === -1 ? radiusOrder.length - 1 : index)
			}, -1)
			return largest === -1 ? null : radii[radiusOrder[largest]]
		},

		radiusTooltip() {
			return [
				this.radiusInfo?.description,
				this.destructive.description,
				this.irreversible.description,
			].filter(Boolean).join('\n')
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

		.footer-left {
			display: flex;
			gap: 4px;
			align-items: center;
			margin-right: auto;
		}
	}
}
</style>
