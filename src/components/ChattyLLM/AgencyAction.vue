<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="agency-action">
		<NcChip type="primary"
			:no-close="true"
			:text="action.name.replace(/_/g, ' ')">
			<template #icon>
				<ToolsIcon :size="16" />
			</template>
		</NcChip>
		<NcChip v-for="(argValue, argName) in action.args"
			:key="argName + argValue"
			class="param"
			:no-close="true"
			:text="getParamText(argName, argValue)"
			:title="getParamText(argName, argValue)" />
	</div>
</template>

<script>
import ToolsIcon from 'vue-material-design-icons/Tools.vue'

import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'

export default {
	name: 'AgencyAction',

	components: {
		NcChip,
		ToolsIcon,
	},

	props: {
		action: {
			type: Object,
			required: true,
		},
	},

	methods: {
		getParamText(argName, argValue) {
			return argName.replace(/_/g, ' ') + ': ' + argValue
		},
	},
}
</script>

<style lang="scss" scoped>
.agency-action {
	display: flex;
	align-items: start;
	gap: 4px;
	flex-wrap: wrap;

	.param {
		max-width: 300px;
	}
}
</style>
