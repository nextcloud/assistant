<!-- SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com> -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<template>
	<div class="text2image-reference">
		<div class="result">
			<Text2ImageDisplay :src="richObject.proxied_url" :prompt="richObject.prompt" />
		</div>
	</div>
</template>

<script>

import Text2ImageDisplay from '../../components/Text2Image/Text2ImageDisplay.vue'

export default {
	name: 'Text2ImageReferenceWidget',

	components: {
		Text2ImageDisplay,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.text2image-reference {
	display: flex;
	flex-direction: column;
	width: 100%;
	padding: 12px;
	.result {
		display: flex;
		flex-direction: column;
		width: 100%;
		border-radius: var(--border-radius);
		margin-top: 8px;
	}
}
</style>
