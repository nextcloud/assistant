<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<audio :src="audioUrl"
		controls
		:autoplay="autoplay"
		:class="{ shadowed: isOutput }" />
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
// import { getRequestToken } from '@nextcloud/auth'

export default {
	name: 'AudioDisplay',

	components: {
	},

	props: {
		fileId: {
			type: Number,
			required: true,
		},
		taskId: {
			type: [Number, null],
			default: null,
		},
		isOutput: {
			type: Boolean,
			default: false,
		},
		autoplay: {
			type: Boolean,
			default: false,
		},
	},

	emits: [],

	data() {
		return {
		}
	},

	computed: {
		audioUrl() {
			// the assistant endpoint gets the file with the correct mimetype
			return this.isOutput
				/*
				? generateOcsUrl('taskprocessing/tasks/{taskId}/file/{fileId}?requesttoken={rToken}', {
					taskId: this.taskId,
					fileId: this.fileId,
					rToken: getRequestToken(),
				})
				*/
				? generateOcsUrl('apps/assistant/api/v1/task/{taskId}/output-file/{fileId}/download', {
					taskId: this.taskId,
					fileId: this.fileId,
				})
				: generateOcsUrl('apps/assistant/api/v1/file/{fileId}/display', { fileId: this.fileId })
		},
	},

	watch: {
	},

	mounted() {
		console.debug('CURRENT TASK', this.taskId)
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
audio {
	border-radius: 100px;
	&.shadowed {
		border: 2px solid var(--color-primary-element);
	}
}
</style>
