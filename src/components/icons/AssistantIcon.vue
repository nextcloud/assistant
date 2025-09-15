<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
			viewBox="-4 -4 32 32"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg"
			transform="scale(1.333)">
			<!-- Split each star into its own group for animation -->
			<g class="star star1">
				<path d="M19,1L17.74,3.75L15,5L17.74,6.26L19,9L20.25,6.26L23,5L20.25,3.75" />
			</g>
			<g class="star star2">
				<path d="M9,4L6.5,9.5L1,12L6.5,14.5L9,20L11.5,14.5L17,12L11.5,9.5" />
			</g>
			<g class="star star3">
				<path d="M19,15L17.74,17.74L15,19L17.74,20.25L19,23L20.25,20.25L23,19L20.25,17.74" />
			</g>
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

<style scoped>
.star {
  transform-origin: center center;
}

.assistant-icon:hover .star1 {
  animation: pulse 0.6s ease forwards;
}

.assistant-icon:hover .star2 {
  animation: pulse 0.6s ease 0.1s forwards;
}

.assistant-icon:hover .star3 {
  animation: pulse 0.6s ease 0.2s forwards;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.4); }
  100% { transform: scale(1); }
}
</style>
