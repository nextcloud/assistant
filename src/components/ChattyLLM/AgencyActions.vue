<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="agency-actions">
		<AgencyAction v-for="action in actions"
			:key="action.id"
			:action="action"
			class="action" />
	</div>
</template>

<script>
import AgencyAction from './AgencyAction.vue'

export default {
	name: 'AgencyActions',

	components: {
		AgencyAction,
	},

	props: {
		actions: {
			type: Array,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.agency-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: stretch;
	gap: 8px;

	.action {
		max-width: 300px;
	}
}
</style>
