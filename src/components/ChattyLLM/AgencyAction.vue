<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="agency-action">
		<div class="action-title">
			<ToolsIcon :size="20" />
			<strong>
				{{ action.name.replace(/_/g, ' ') }}
			</strong>
		</div>
		<span v-for="(argValue, argName) in action.args"
			:key="argName + argValue"
			class="param"
			:title="getParamText(argName, argValue)">
			{{ getParamText(argName, argValue) }}
		</span>
	</div>
</template>

<script>
import ToolsIcon from 'vue-material-design-icons/Tools.vue'

export default {
	name: 'AgencyAction',

	components: {
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
	flex-direction: column;
	align-items: start;
	gap: 4px;

	border-radius: var(--border-radius-large);
	background-color: var(--color-primary-element-light-hover);
	padding: 8px;

	.action-title {
		display: flex;
		align-items: center;
	}

	.param {
		white-space: nowrap;
		text-overflow: ellipsis;
		width: 100%;
		overflow: hidden;
	}
}
</style>
