<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon assistant-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 24 24"
			version="1.1"
			viewBox="0 0 24 24"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<path d="M12,2L1,21H23M12,6L19.53,19H4.47" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'AssistantIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
