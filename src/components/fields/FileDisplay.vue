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
		this.getFileInfo()
	},

	methods: {
		getFileInfo() {
			const url = generateOcsUrl('apps/assistant/api/v1/file/{fileId}/info', { fileId: this.fileId })
			axios.get(url)
				.then((response) => {
					this.fileInfo = response.data.ocs.data
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
