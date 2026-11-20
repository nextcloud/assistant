<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cc-output">
		<div class="cc-output__text">
			<TextField
				field-key="cc-output-text"
				:value="output.output"
				:field="outputShape.output"
				:is-output="true" />
		</div>
		<div class="cc-output__sources">
			<label class="cc-output__sources__label">
				{{ outputShape.sources.description }}
			</label>
			<div class="cc-output__sources__list">
				<a v-for="source in sources"
					:key="source.url"
					class="select-option"
					:href="source.url"
					target="_blank">
					<NcChip :text="source.label" :no-close="true">
						<template #icon>
							<NcAvatar
								:size="24"
								:url="source.icon"
								:display-name="source.label" />
						</template>
					</NcChip>
				</a>
			</div>
		</div>
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcChip from '@nextcloud/vue/components/NcChip'

import TextField from '../fields/TextField.vue'

export default {
	name: 'ContextChatOutputForm',

	components: {
		NcAvatar,
		NcChip,
		TextField,
	},

	props: {
		outputShape: {
			type: Object,
			required: true,
		},
		output: {
			type: Object,
			required: true,
		},
	},

	computed: {
		sources() {
			try {
				return this.output?.sources?.map(JSON.parse) ?? []
			} catch (e) {
				console.error('Failed to parse sources', e)
				return []
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cc-output {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 8px;

	.advanced {
		width: 100%;
	}

	&__text {
		width: 100%;
	}

	&__sources {
		display: flex;
		flex-direction: column;

		&__list {
			display: flex;
			flex-wrap: wrap;
			align-content: flex-start;
			gap: 8px;
			min-width: 400px;
			max-height: 200px;
			overflow-y: auto;
			padding: 8px 12px;
			border: 2px solid var(--color-primary-element);
			border-radius: var(--border-radius-large);
		}

		:deep(.nc-chip) {
			background-color: var(--color-primary-element-light);
		}

		.select-option {
			display: inline-flex;
			max-width: 100%;
			text-decoration: none;
			color: inherit;
		}
	}
}
</style>
