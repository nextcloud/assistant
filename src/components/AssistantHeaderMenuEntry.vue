<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="assistant"
		class="header-menu">
		<NcButton
			class="trigger"
			variant="secondary"
			:title="t('assistant', 'Nextcloud Assistant')"
			@click="$emit('click')">
			<AssistantIcon class="menu-icon" :size="20" />
		</NcButton>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'AssistantHeaderMenuEntry',

	components: {
		AssistantIcon,
		NcButton,
	},

	emits: ['click'],

	data() {
		return {
		}
	},

	computed: {
	},

	mounted() {
	},

	beforeDestroy() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.header-menu {
	width: var(--header-height);
	height: var(--header-height);
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: .85;
	&:hover {
		opacity: 1;
	}

	.trigger {
		width: 100%;
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		background-color: rgba(0,0,0,0);
		border: none;
		filter: none !important;
		color: var(--color-primary-text) !important;
		&:hover {
			background-color: rgba(0,0,0,0) !important;
		}
		.menu-icon {
			color: var(--color-background-plain-text) !important;
		}
	}
}
</style>
