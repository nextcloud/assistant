<!-- SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com> -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<template>
	<NcContent app-name="assistant">
		<NcAppContent class="page">
			<div class="generation-dialog">
				<h2>
					{{ t('assistant', 'Image generation') }}
				</h2>
				<div class="image">
					<Text2ImageDisplay :image-gen-id="imageGenId" :force-edit-mode="forceEditMode" />
				</div>
				<div class="button-wrapper">
					<NcButton
						type="primary"
						:aria-label="t('assistant', 'Copy the link to this generation to clipboard')"
						:title="t('assistant', 'Copy link to clipboard')"
						@click="onCopy">
						{{ t('assistant', 'Copy link to clipboard') }}
						<template #icon>
							<ClipboardCheckOutlineIcon v-if="copied" />
							<ContentCopyIcon v-else />
						</template>
					</NcButton>
				</div>
			</div>
		</NcAppContent>
	</NcContent>
</template>
<script>
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'

import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

import Text2ImageDisplay from '../../components/Text2Image/Text2ImageDisplay.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import VueClipboard from 'vue-clipboard2'
import Vue from 'vue'

Vue.use(VueClipboard)

export default {
	name: 'Text2ImageGenerationPage',
	components: {
		NcContent,
		NcAppContent,
		NcButton,
		Text2ImageDisplay,
		ContentCopyIcon,
		ClipboardCheckOutlineIcon,
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
			copied: false,
		}
	},
	mounted() {
	},
	methods: {
		onClose() {
			this.$emit('close')
		},
		async onCopy() {
			try {
				await this.$copyText(window.location.href)
				this.copied = true
				showSuccess(t('assistant', 'Image link copied to clipboard'))
				setTimeout(() => {
					this.copied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Image link could not be copied to clipboard'))
			}
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
			margin-top: 8px;
			max-width: 600px;
			border: 3px solid var(--color-border);
			border-radius: var(--border-radius-large);
			padding: 12px;
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
