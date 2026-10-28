<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="image-display"
		:class="{ 'with-name': showName && fileName }">
		<img :src="imageUrl"
			:style="style"
			:class="{ clickable }">
		<span v-if="showName && fileName"
			class="image-name"
			:title="fileName">
			{{ fileName }}
		</span>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ImageDisplay',

	components: {
	},

	inject: [
		'providedCurrentTaskId',
	],

	props: {
		taskId: {
			type: [Number, null],
			default: null,
		},
		fileId: {
			type: Number,
			required: true,
		},
		isOutput: {
			type: Boolean,
			default: false,
		},
		borderRadius: {
			type: [Number, null],
			default: null,
		},
		clickable: {
			type: Boolean,
			default: false,
		},
		showName: {
			type: Boolean,
			default: false,
		},
	},

	emits: [],

	data() {
		return {
			fileInfo: {},
		}
	},

	computed: {
		myCurrentTaskId() {
			return this.taskId ?? this.providedCurrentTaskId()
		},
		fileName() {
			return this.fileInfo?.name
		},
		imageUrl() {
			return this.isOutput
				? generateOcsUrl('apps/assistant/api/v1/task/{taskId}/output-file/{fileId}/preview?x=300&y=300', {
						taskId: this.myCurrentTaskId,
						fileId: this.fileId,
					})
				: generateOcsUrl('apps/assistant/api/v1/file/{fileId}/display', { fileId: this.fileId })
		},
		style() {
			return {
				'border-radius': this.borderRadius ? (this.borderRadius + 'px') : 'var(--border-radius-large)',
			}
		},
	},

	watch: {
	},

	mounted() {
		console.debug('CURRENT TASK', this.myCurrentTaskId)
		if (this.showName && this.isOutput && this.myCurrentTaskId !== null && this.myCurrentTaskId !== undefined) {
			this.getOutputFileInfo()
		}
	},

	methods: {
		/**
		 * Fetch the output file info through the task-scoped endpoint.
		 * Retries once so a transient failure does not leave the caption
		 * missing for the component's lifetime.
		 */
		getOutputFileInfo(retry = true) {
			const url = generateOcsUrl('apps/assistant/api/v1/task/{taskId}/output-file/{fileId}/info', {
				taskId: this.myCurrentTaskId,
				fileId: this.fileId,
			})
			axios.get(url)
				.then((response) => {
					this.fileInfo = response.data.ocs.data
				})
				.catch(() => {
					if (retry) {
						this.getOutputFileInfo(false)
					}
				})
		},
	},
}
</script>

<style lang="scss">
.image-display {
	display: flex;
	img {
		// we could use object-fit:cover instead and keep width=200 but the image preview would just show a portion of the image
		// object-fit: cover;
		// width: 200px;
		width: auto;
		height: 200px;

		&.clickable {
			cursor: pointer !important;
		}
	}
	.image-name {
		max-width: 200px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	&.with-name {
		flex-direction: column;
		align-items: center;
	}
}
</style>
