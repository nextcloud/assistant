<template>
	<div class="display-container">
		<NcLoadingIcon v-if="loadingInfo"
			:size="20"
			class="icon" />
		<div v-else-if="failed">
			{{ t('assistant', 'Failed to get images') }}
		</div>
		<div v-else
			class="inline-images">
			<img v-for="url in imageUrls"
				:key="url"
				class="image"
				:src="url"
				@error="onError">
		</div>
	</div>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'Text2ImageInlineDisplay',

	components: {
		NcLoadingIcon,
	},

	props: {
		imageGenId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			loadingInfo: false,
			failed: false,
			imageUrls: [],
		}
	},

	computed: {
	},
	watch: {
		imageGenId() {
			this.failed = false
			this.imageUrls = []
			this.getImageGenInfo()
		},
	},
	mounted() {
		this.getImageGenInfo()
	},
	unmounted() {
	},
	methods: {
		getImageGenInfo() {
			this.imageUrls = []
			this.loadingInfo = true
			const url = generateOcsUrl('/apps/assistant/api/v1/i/info/{imageGenId}', { imageGenId: this.imageGenId })
			axios.get(url)
				.then((response) => {
					if (response.status === 200) {
						const data = response.data?.ocs?.data
						if (data?.files !== undefined) {
							if (data.files.length === 0) {
								this.errorMsg = t('assistant', 'This generation has no visible images')
								this.failed = true
							} else {
								this.imageUrls = data.files.map(file => {
									return generateOcsUrl('/apps/assistant/api/v1/i/{imageGenId}/{fileId}', { imageGenId: this.imageGenId, fileId: file.id })
								})
							}
						} else {
							this.errorMsg = t('assistant', 'Unexpected server response')
							this.failed = true
						}
					} else {
						console.error('Unexpected response status: ' + response.status)
						this.errorMsg = t('assistant', 'Unexpected server response')
						this.failed = true
					}
				})
				.catch((error) => {
					this.onError(error)
				})
				.then(() => {
					this.loadingInfo = false
				})
		},
		onError(error) {
			// If error response status is 429 let the user know that they are being rate limited
			if (error.response?.status === 429) {
				this.errorMsg = t('assistant', 'Rate limit reached. Please try again later.')
				this.failed = true
			} else if (error.response?.data !== undefined) {
				this.errorMsg = error.response.data.error
				this.failed = true
			} else {
				console.error('Could not handle response error: ' + error)
				this.errorMsg = t('assistant', 'Unknown server query error')
				this.failed = true
			}

		},
	},
}
</script>

<style scoped lang="scss">
.display-container {
	display: flex;
	flex-direction: column;
	align-items: start;
	justify-content: center;
	.inline-images {
		display: flex;
		gap: 4px;
		.image {
			width: auto;
			height: 30px;
			border-radius: var(--border-radius);
		}
	}
}
</style>
