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
					<NcPopover v-if="radiusInfo">
						<template #trigger>
							<NcButton variant="tertiary"
								:title="radiusInfo.description"
								:text="radiusInfo.label"
								:aria-label="radiusInfo.label"
								class="help">
								<template #icon>
									<component :is="radiusInfo.icon" :size="20" />
								</template>
							</NcButton>
						</template>
						<p class="popover-content">
							{{ radiusInfo.description }}
						</p>
					</NcPopover>
					<NcPopover v-if="destructive.destructive">
						<template #trigger>
							<NcButton variant="tertiary"
								:title="destructive.description"
								:aria-label="destructive.description"
								class="help">
								<template #icon>
									<DeleteAlertOutlineIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<p class="popover-content">
							{{ destructive.description }}
						</p>
					</NcPopover>
					<NcPopover v-if="irreversible.irreversible">
						<template #trigger>
							<NcButton variant="tertiary"
								:title="irreversible.description"
								:aria-label="irreversible.description"
								class="help">
								<template #icon>
									<RestoreAlertIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<p class="popover-content">
							{{ irreversible.description }}
						</p>
					</NcPopover>
					<NcPopover v-if="itemCount.itemCount > 1">
						<template #trigger>
							<NcButton variant="tertiary"
								:title="itemCount.description"
								:aria-label="itemCount.description"
								:text="itemCount.label"
								class="help">
								<template #icon>
									<TallyMark5Icon :size="20" />
								</template>
							</NcButton>
						</template>
						<p class="popover-content">
							{{ itemCount.description }}
						</p>
					</NcPopover>
				</div>
				<NcPopover>
					<template #trigger>
						<NcButton variant="tertiary"
							:title="hint"
							:aria-label="t('assistant', 'More information')"
							class="help">
							<template #icon>
								<InformationOutlineIcon :size="20" />
							</template>
						</NcButton>
					</template>
					<p class="popover-content">
						{{ hint }}
					</p>
				</NcPopover>
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
import DeleteAlertOutlineIcon from 'vue-material-design-icons/DeleteAlertOutline.vue'
import RestoreAlertIcon from 'vue-material-design-icons/RestoreAlert.vue'
import AssistantIcon from '../icons/AssistantIcon.vue'

import AgencyActions from './AgencyActions.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

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
		NcPopover,
		CloseIcon,
		AccountOutlineIcon,
		AccountMultipleOutlineIcon,
		AccountGroupOutlineIcon,
		EarthIcon,
		InformationOutlineIcon,
		TallyMark5Icon,
		DeleteAlertOutlineIcon,
		RestoreAlertIcon,
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
			// the total is only exact if every action reports an exact count;
			// counts not flagged as exact (e.g. counting stopped at a limit, or older backends) are lower bounds
			let itemCount = 0
			let exact = true
			for (const action of this.actions) {
				if (Number.isFinite(action?.item_count)) {
					itemCount += action.item_count
					if (action?.item_count_exact !== true) {
						exact = false
					}
				} else {
					exact = false
				}
			}
			return {
				itemCount,
				exact,
				label: exact ? String(itemCount) : itemCount + '+',
				description: exact
					// TRANSLATORS Additional description for pending AI agent actions to be confirmed by the user
					? n('assistant', '%n item is affected by these actions.', '%n items are affected by these actions.', itemCount)
					// TRANSLATORS Additional description for pending AI agent actions to be confirmed by the user, when the exact number of affected items is unknown
					: n('assistant', 'At least %n item is affected by these actions.', 'At least %n items are affected by these actions.', itemCount),
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
	},
}
</script>

<style lang="scss">
.agency-confirmation > div {
	width: 100%;
}
</style>

<style lang="scss" scoped>
.popover-content {
	padding: 8px 12px;
	max-width: 300px;
}

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
