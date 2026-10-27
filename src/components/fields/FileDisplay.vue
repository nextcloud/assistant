<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="file-display">
		<div class="preview">
			<img :src="imageUrl" :class="{ clickable }">
			<span v-if="fileName"
				class="file-name"
				:title="fileName">
				{{ fileName }}
			</span>
		</div>
	</div>
</template>

<script>
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'FileDisplay',

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
		clickable: {
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
		imageUrl() {
			// TODO find a way to get a preview for an output file (no mimetype because it's deduced from the name in core)
			return this.isOutput
				? generateOcsUrl('apps/assistant/api/v1/task/{taskId}/output-file/{fileId}/preview', {
						taskId: this.myCurrentTaskId,
						fileId: this.fileId,
					})
				: generateUrl('core/preview?fileId={fileId}&x=100&y=100&mimeFallback=true&a=0', { fileId: this.fileId })
		},
		fileName() {
			return this.fileInfo?.name
		},
	},

	watch: {
	},

	mounted() {
		console.debug('CURRENT TASK', this.myCurrentTaskId)
		if (!this.isOutput) {
			this.getFileInfo()
		} else if (this.myCurrentTaskId !== null && this.myCurrentTaskId !== undefined) {
			this.getOutputFileInfo()
		}
	},

	methods: {
		getFileInfo() {
			const url = generateOcsUrl('apps/assistant/api/v1/file/{fileId}/info', { fileId: this.fileId })
			axios.get(url)
				.then((response) => {
					this.fileInfo = response.data.ocs.data
				})
		},
		/**
		 * Fetch the output file info through the task-scoped endpoint.
		 * Retries once so a transient failure does not leave the chip
		 * unnamed for the component's lifetime.
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
.file-display {
	display: flex;
	.preview {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		img {
			width: 100px;

			&.clickable {
				cursor: pointer !important;
			}
		}
		.file-name {
			max-width: 100px;
			max-height: 100px;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}
</style>
