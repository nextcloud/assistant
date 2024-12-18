<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="image-display">
		<img :src="imageUrl" :style="style">
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

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
	},

	emits: [],

	data() {
		return {
		}
	},

	computed: {
		myCurrentTaskId() {
			return this.taskId ?? this.providedCurrentTaskId()
		},
		imageUrl() {
			return this.isOutput
				? generateOcsUrl('taskprocessing/tasks/{taskId}/file/{fileId}', {
					taskId: this.myCurrentTaskId,
					fileId: this.fileId,
					rToken: getRequestToken(),
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
	},

	methods: {
	},
}
</script>

<style lang="scss">
.image-display {
	display: flex;
	img {
		width: 200px;
		height: 200px;
	}
}
</style>
