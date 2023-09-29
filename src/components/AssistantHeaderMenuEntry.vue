<template>
	<div id="assistant"
		class="header-menu"
		:title="t('assistant', 'Nextcloud Assistant')">
		<a
			href="#"
			class="trigger"
			@click="$emit('click')">
			<AssistantIcon class="menu-icon" :size="20" />
		</a>
	</div>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'

export default {
	name: 'AssistantHeaderMenuEntry',

	components: {
		AssistantIcon,
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
		.menu-icon {
			color: var(--color-primary-text) !important;
		}
	}
}
</style>
