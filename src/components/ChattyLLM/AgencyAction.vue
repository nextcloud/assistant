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
		<span v-for="(argValue, argName) in previewArguments"
			ref="params"
			:key="argName"
			class="param"
			:title="getParamText(argName, argValue)">
			<strong>{{ getParamName(argName) }}:</strong> {{ getParamValue(argValue) }}
		</span>
		<span v-if="hiddenArgsCount > 0" class="hidden-params">
			{{ n('assistant', '+%n more parameter', '+%n more parameters', hiddenArgsCount) }}
		</span>
		<NcButton v-if="needsDetails"
			class="details"
			@click="detailsOpen = true">
			{{ t('assistant', 'Details') }}
			<template #icon>
				<ArrowExpandIcon />
			</template>
		</NcButton>
		<NcDialog v-model:open="detailsOpen"
			:name="action.name"
			size="normal">
			<p v-for="(argValue, argName) in action.args"
				:key="argName"
				class="details-param">
				<strong>{{ getParamName(argName) }}:</strong> {{ getParamValue(argValue) }}
			</p>
			<template #actions>
				<NcButton variant="tertiary"
					@click="detailsOpen = false">
					{{ t('assistant', 'Close') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import ArrowExpandIcon from 'vue-material-design-icons/ArrowExpand.vue'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'

const maxDisplayedArgs = 3

export default {
	name: 'AgencyAction',

	components: {
		NcIconSvgWrapper,
		NcButton,
		NcDialog,
		ArrowExpandIcon,
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
			detailsOpen: false,
			hasTruncatedParams: false,
		}
	},

	computed: {
		hiddenArgsCount() {
			return Math.max(0, Object.keys(this.action.args).length - maxDisplayedArgs)
		},
		needsDetails() {
			return this.hiddenArgsCount > 0 || this.hasTruncatedParams
		},
		previewArguments() {
			return Object.fromEntries(Object.entries(this.action.args).slice(0, maxDisplayedArgs))
		},
	},

	mounted() {
		this.getIcon()
		// the observer callback also runs once right after observe(), which covers the initial render
		this.resizeObserver = new ResizeObserver(this.updateTruncation)
		this.resizeObserver.observe(this.$el)
	},

	beforeUnmount() {
		this.resizeObserver?.disconnect()
	},

	methods: {
		getParamName(argName) {
			return argName.replace(/_/g, ' ').replace(/(^|\s)\S/g, (char) => char.toUpperCase())
		},
		getParamValue(argValue) {
			return Array.isArray(argValue) ? argValue.map(this.formatValue).join(', ') : this.formatValue(argValue)
		},
		formatValue(value) {
			return typeof value === 'object' && value !== null ? JSON.stringify(value) : String(value)
		},
		getParamText(argName, argValue) {
			return this.getParamName(argName) + ': ' + this.getParamValue(argValue)
		},
		updateTruncation() {
			const params = this.$refs.params ?? []
			const hasTruncatedParams = params.some(el => el.scrollWidth > el.clientWidth)
			if (hasTruncatedParams === this.hasTruncatedParams) {
				return
			}
			// defer to the next frame to avoid "ResizeObserver loop" warnings
			window.requestAnimationFrame(() => {
				this.hasTruncatedParams = hasTruncatedParams
			})
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

	.hidden-params {
		color: var(--color-text-maxcontrast);
	}

	.details {
		align-self: center;
	}
}

.details-param {
	margin-bottom: 8px;
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}
</style>
