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
		<span v-for="(argValue, argName) in actionArguments"
			:key="argName + argValue"
			class="param"
			:title="getParamText(argName, argValue)">
			{{ getParamText(argName, argValue) }}
		</span>
		<NcButton v-if="needsExpand"
			class="expand"
			@click="expanded = !expanded">
			{{ expanded ? t('assistant', 'Less') : t('assistant', 'More') }}
			<template #icon>
				<ChevronDoubleUpIcon v-if="expanded" />
				<ChevronDoubleDownIcon v-else />
			</template>
		</NcButton>
	</div>
</template>

<script>
import ChevronDoubleDownIcon from 'vue-material-design-icons/ChevronDoubleDown.vue'
import ChevronDoubleUpIcon from 'vue-material-design-icons/ChevronDoubleUp.vue'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcButton from '@nextcloud/vue/components/NcButton'

const maxDisplayedArgs = 3

export default {
	name: 'AgencyAction',

	components: {
		NcIconSvgWrapper,
		NcButton,
		ChevronDoubleDownIcon,
		ChevronDoubleUpIcon,
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
			expanded: false,
		}
	},

	computed: {
		needsExpand() {
			return Object.keys(this.action.args).length > maxDisplayedArgs
		},
		actionArguments() {
			if (this.needsExpand && !this.expanded) {
				const keys = Object.keys(this.action.args).slice(0, maxDisplayedArgs)
				return keys.reduce((acc, val) => {
					acc[val] = this.action.args[val]
					return acc
				}, {})
			}
			return this.action.args
		},
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

	.expand {
		align-self: center;
	}
}
</style>
