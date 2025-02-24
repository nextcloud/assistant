<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="agency-action">
		<div class="action-title">
			<NcIconSvgWrapper :path="iconPath" :name="action.name" />
			<strong>
				{{ action.name }}
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
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'

export default {
	name: 'AgencyAction',

	components: {
		NcIconSvgWrapper,
	},

	props: {
		action: {
			type: Object,
			required: true,
		},
	},

	data: () => {
		return {
			iconPath: null,
		}
	},

	mounted() {
		this.getIcon()
	},

	methods: {
		getParamText(argName, argValue) {
			return argName.replace(/_/g, ' ') + ': ' + argValue
		},
		async getIcon() {
			const { ['mdi' + (this.action.icon ?? 'Tools')]: icon } = await import('@mdi/js')
			this.iconPath = icon
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
		gap: 4px;
	}

	.param {
		white-space: nowrap;
		text-overflow: ellipsis;
		width: 100%;
		overflow: hidden;
	}
}
</style>
