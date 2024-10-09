<template>
	<div id="assistant"
		class="header-menu">
		<NcButton
			class="trigger"
			type="secondary"
			:title="t('assistant', 'Nextcloud Assistant')"
			@click="$emit('click')">
			<AssistantIcon class="menu-icon" :size="20" />
		</NcButton>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'AssistantHeaderMenuEntry',

	components: {
		AssistantIcon,
		NcButton,
	},

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
