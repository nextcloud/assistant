<!-- SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com> -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<template>
	<NcContent app-name="assistant">
		<NcAppContent class="page">
			<div class="generation-dialog">
				<h2>
					{{ t('assistant', 'Image generation') }}
				</h2>
				<div v-if="generationUrl !== null" class="image">
					<Text2ImageDisplay :src="generationUrl" :force-edit-mode="forceEditMode" />
				</div>
				<div class="button-wrapper">
					<NcButton
						type="primary"
						@click="copyToClipboard">
						{{ t('assistant', 'Copy link to clipboard') }}
					</NcButton>
				</div>
			</div>
		</NcAppContent>
	</NcContent>
</template>
<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import Text2ImageDisplay from '../../components/Text2Image/Text2ImageDisplay.vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Text2ImageGenerationPage',
	components: {
		NcContent,
		NcAppContent,
		NcButton,
		Text2ImageDisplay,
	},
	props: {
		imageGenId: {
			type: String,
			required: true,
		},
		forceEditMode: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			generationUrl: null,
		}
	},
	mounted() {
		this.generateUrl()
	},
	methods: {
		onClose() {
			this.$emit('close')
		},
		generateUrl() {
			this.generationUrl = generateUrl('/apps/assistant/i/info/' + this.imageGenId)
		},
		copyToClipboard() {
			navigator.clipboard.writeText(this.generationUrl)
		},
	},
}
</script>
<style scoped lang="scss">

.page {
	justify-content: center;
	align-content: center;
	.generation-dialog {
		margin: 12px;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 12px 12px 12px 12px;
		overflow-x: hidden;

		h2 {
			display: flex;
			align-items: center;
		}

		.image {
			display: flex;
			flex-direction: column;
			border-radius: var(--border-radius);
			margin-top: 8px;
		}

		.button-wrapper {
			display: flex;
			flex-direction: column;
			margin-top: 24px;
			margin-bottom: 48px;
		}
	}
}

</style>
