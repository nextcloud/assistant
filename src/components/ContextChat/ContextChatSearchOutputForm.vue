<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cc-output__sources">
		<label class="cc-output__sources__label">
			{{ outputShape.sources.description }}
		</label>
		<ContextChatSource v-for="(source, i) in sources"
			:key="'source-' + i + '-' + source.url"
			:source="source" />
	</div>
</template>

<script>
import ContextChatSource from './ContextChatSource.vue'

export default {
	name: 'ContextChatSearchOutputForm',

	components: {
		ContextChatSource,
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
.cc-output__sources {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 8px;
}
</style>
