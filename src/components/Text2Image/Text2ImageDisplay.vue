<!-- SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com> -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

<template>
	<div class="display-container">
		<div class="title">
			<div class="icon-and-text">
				<NcLoadingIcon v-if="loading"
					:size="20"
					class="icon" />
				<AssistantIcon v-else
					:size="20"
					class="icon" />
				<strong class="app-name">
					{{ t('assistant', 'Image generation') + ':' }}
				</strong>
				{{ prompt }}
			</div>
			<Cog v-if="isOwner"
				:size="30"
				class="edit-icon"
				:class="{ 'active': editModeEnabled}"
				:title="t('assistant', 'Edit visible images')"
				@click="toggleEditMode" />
		</div>
		<div v-if="editModeEnabled && isOwner">
			<div v-if="imageUrls.length > 0 && !failed" class="image-list">
				<div v-for="(imageUrl, index) in imageUrls"
					:key="index"
					class="image-container"
					@mouseover="hoveredIndex = index"
					@mouseout="hoveredIndex = -1">
					<div class="checkbox-container" :class="{ 'hovering': hoveredIndex === index }">
						<input v-model="fileVisStatusArray[index].visible"
							:v-show="!imgLoadedList[index]"
							type="checkbox"
							:title="t('assistant', 'Click to toggle generation visibility')"
							@change="onCheckboxChange()">
					</div>
					<div class="image-wrapper" :class="{ 'deselected': !fileVisStatusArray[index].visible }">
						<img
							class="image-editable"
							:src="imageUrl"
							:title="t('assistant', 'Click to toggle generation visibility')"
							@load="onImageLoad(index)"
							@click="toggleCheckbox(index)"
							@error="onError">
					</div>
				</div>
			</div>
		</div>
		<div v-else>
			<div v-if="imageUrls.length > 0 && !failed"
				class="image-list">
				<div v-for="(imageUrl, index) in imageUrls"
					:key="index"
					class="image-container">
					<div v-show="!isOwner || fileVisStatusArray[index].visible" class="image-wrapper">
						<img
							class="image-non-editable"
							:src="imageUrl"
							:title="t('assistant', 'Generated image')"
							@load="onImageLoad(index)"
							@error="onError">
					</div>
				</div>
				<div v-if="!hasVisibleImages" class="error_msg">
					{{ t('assistant', 'This generation has no visible images') }}
				</div>
			</div>
		</div>
		<div v-if="!failed && waitingInBg"
			class="processing-notification-container">
			<div v-if="timeUntilCompletion !== null" class="processing-notification">
				<InformationOutlineIcon :size="20" class="icon" />
				{{ t('assistant', 'Estimated generation time left: ') + timeUntilCompletion + '. ' }}
				{{ t('assistant', 'The generated image is shown once ready.') }}
			</div>
			<div v-else class="processing-notification">
				<InformationOutlineIcon :size="20" class="icon" />
				{{ t('assistant', 'This image generation was scheduled to run in the background.') }}
				{{ t('assistant', 'The generated image is shown once ready.') }}
			</div>
		</div>
		<span v-if="failed" class="error_msg">
			{{ errorMsg }}
		</span>
	</div>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import Cog from 'vue-material-design-icons/Cog.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import axios from '@nextcloud/axios'
import AssistantIcon from '../icons/AssistantIcon.vue'
import { generateUrl } from '@nextcloud/router'
import humanizeDuration from 'humanize-duration'

export default {
	name: 'Text2ImageDisplay',

	components: {
		NcLoadingIcon,
		InformationOutlineIcon,
		AssistantIcon,
		Cog,
	},

	props: {
		src: {
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
			prompt: '',
			loadingImages: true,
			imgLoadedList: [],
			timeUntilCompletion: null,
			failed: false,
			imageUrls: [],
			isOwner: false,
			errorMsg: t('assistant', 'Image generation failed'),
			closed: false,
			fileVisStatusArray: [],
			hoveredIndex: -1,
			hovered: false,
			editModeEnabled: false,
			waitingInBg: false,
		}
	},

	computed: {
		loading() {
			// Will turn to false once all images have loaded or if something fails
			return this.loadingImages && !this.failed && this.imageUrls.length > 0
		},
		hasVisibleImages() {
			if (this.isOwner) {
				return this.fileVisStatusArray.some(status => status.visible)
			} else {
				return this.imageUrls.length > 0
			}
		},
	},
	mounted() {
		this.getImageGenInfo()
		this.editModeEnabled = this.forceEditMode
	},
	unmounted() {
		this.closed = true
	},
	methods: {
		onImageLoad(index) {
			this.imgLoadedList[index] = true

			if (this.imgLoadedList.every((loaded) => loaded)) {
				this.loadingImages = false
			}
		},
		getImages(imageGenId, fileIds) {
			this.loadingImages = true
			this.imageUrls = []
			this.imgLoadedList = []
			this.fileVisStatusArray = fileIds

			// Loop through all the fileIds and get the images:
			fileIds.forEach((fileId) => {
				this.imageUrls.push(generateUrl('/apps/assistant/i/' + imageGenId + '/' + fileId.id))
				this.imgLoadedList.push = false
			})
		},
		getImageGenInfo() {
			let success = false
			axios.get(this.src)
				.then((response) => {
					if (response.status === 200) {
						if (response.data?.files !== undefined) {
							this.waitingInBg = false

							if (response.data.files.length === 0) {
								this.errorMsg = t('assistant', 'This generation has no visible images')
								this.failed = true
								this.imgLoadedList = []
							} else {
								this.prompt = response.data.prompt
								this.isOwner = response.data.is_owner
								success = true
								this.getImages(response.data.image_gen_id, response.data.files)
								this.onGenerationReady()
							}
						} else if (response.data?.processing !== undefined) {
							this.waitingInBg = true
							this.$emit('processing')
							this.updateTimeUntilCompletion(response.data.processing)
						} else {
							this.errorMsg = t('assistant', 'Unexpected server response')
							this.failed = true
							this.imgLoadedList = []
						}
					} else {
						console.error('Unexpected response status: ' + response.status)
						this.errorMsg = t('assistant', 'Unexpected server response')
						this.failed = true
						this.imgLoadedList = []
					}
					// If we didn't succeed in loading the image gen info yet, try again
					if (!success && !this.failed && !this.closed) {
						setTimeout(this.getImageGenInfo, 3000)
					}
				})
				.catch((error) => {
					this.onError(error)
				})
		},
		updateTimeUntilCompletion(completionTimeStamp) {
			// AFAIK there's no trivial way to do this with a computed property unless timers/intervals
			// are used, so we might as well do this with a method:
			const timeDifference = new Date(completionTimeStamp * 1000) - new Date()
			// If the time difference is less than 5 minutes, don't show the time left
			// (as we don't know when the scheduled job will start exactly)
			if (timeDifference < 5 * 60000) {
				this.timeUntilCompletion = null
				return
			}

			this.timeUntilCompletion = humanizeDuration(timeDifference,
				{ units: ['h', 'm'], language: OC.getLanguage(), fallbacks: ['en'], round: true })

			// Schedule next update:
			if (!this.closed) {
				setTimeout(() => {
					this.updateTimeUntilCompletion(completionTimeStamp)
				}, 30000)
			}
		},
		onError(error) {
			// If error response status is 429 let the user know that they are being rate limited
			if (error.response?.status === 429) {
				this.errorMsg = t('assistant', 'Rate limit reached. Please try again later.')
				this.failed = true
				this.imgLoadedList = []
			} else if (error.response?.data !== undefined) {
				this.errorMsg = error.response.data.error
				this.failed = true
				this.imgLoadedList = []
			} else {
				console.error('Could not handle response error: ' + error)
				this.errorMsg = t('assistant', 'Unknown server query error')
				this.failed = true
				this.imgLoadedList = []
			}
			this.$emit('failed')

		},
		onGenerationReady() {
			this.$emit('ready')
		},
		onCheckboxChange() {
			const url = generateUrl('/apps/assistant/i/visibility/' + this.src.split('/').pop())

			axios.post(url, {
				fileVisStatusArray: this.fileVisStatusArray,
			})
				.then((response) => {
					if (response.status === 200) {
						// console.log('Successfully updated visible images')
					} else {
						console.error('Unexpected response status: ' + response.status)
					}
				})
				.catch((error) => {
					console.error('Could not update visible images: ' + error)
				})
		},
		toggleCheckbox(index) {
			this.fileVisStatusArray[index].visible = !this.fileVisStatusArray[index].visible
			this.onCheckboxChange()
		},
		toggleEditMode() {
			this.editModeEnabled = !this.editModeEnabled
		},
	},
}
</script>

<style scoped lang="scss">
.display-container {
	display: flex;
	flex-direction: column;
	max-width: 600px;
	width: 100%;
	align-items: center;
	justify-content: center;
	.edit-icon {
		position: static;
		opacity: 0.2;
		transition: opacity 0.2s ease-in-out;
		cursor: pointer;
	}

	.edit-icon.active {
		opacity: 1;
		cursor: pointer;
	}

	.image-list {
		display: flex;
		flex-direction: column;
		flex-wrap: wrap;
		justify-content: center;
	}

	.image-container {
		display: flex;
		flex-direction: column;
		position: relative;
		justify-content: center;
	}

	.checkbox-container {
		position: absolute;
		top: 4%;
		right: 4%;
		z-index: 1;
		opacity: 0.2;
		transition: opacity 0.2s ease-in-out;
		> input {
			cursor: pointer;
		}
	}
	.checkbox-container.hovering {
		opacity: 1;
	}

	/*.checkbox {
		cursor: pointer;
	}*/

	.image-wrapper {
		display: flex;
		flex-direction: column;
		position: relative;
		max-width: 100%;
		height: 100%;
		margin-top: 12px;
		filter: none;
		transition: filter 0.2s ease-in-out;
	}

	.image-wrapper.deselected {
		filter: grayscale(100%) brightness(50%);
	}

	.image-editable {
		display: flex;
		width: 100%;
		height: 100%;
		min-width: 400px;
		object-fit:contain;
		cursor: pointer;
		border-radius: var(--border-radius);
	}

	.image-non-editable {
		display: flex;
		width: 100%;
		height: 100%;
		min-width: 400px;
		object-fit:contain;
	}

	.title {
		width: 100%;
		display: flex;
		flex-direction: row;
		margin-top: 0;

		.icon-and-text {
			width: 100%;
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: start;
			margin-right: 8px;

			.app-name {
				margin-right: 8px;
				white-space: nowrap;
			}
		}

		.icon {
			margin-right: 8px;
		}
	}

	.processing-notification-container {
		width: 100%;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		margin-top: 24px;

		.processing-notification {
			display: flex;
			flex-direction: row;
			margin-top: 24px;
			width: 90%;
			align-items: center;
			justify-content: center;
			// Add a border
			border: 3px solid var(--color-border);
			border-radius: var(--border-radius-large);
			padding: 12px;
			// Reduce the font size
			font-size: 0.8rem;
			// Add some space between the icon and the text on the same line
			column-gap: 24px;
		}
	}

	.error_msg {
		color: var(--color-error);
		font-weight: bold;
		margin-bottom: 24px;
		align-self: center;

	}
}
</style>
