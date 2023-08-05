<template>
	<div id="assistant"
		class="header-menu"
		:title="t('textprocessing_assistant', 'Nextcloud assistant')">
		<a
			href="#"
			@click="$emit('click')">
			<AssistantIcon :size="20" />
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
}
</style>
