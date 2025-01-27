<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<audio :src="audioUrl"
		controls />
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

export default {
	name: 'AudioDisplay',

	components: {
	},

	inject: [
		'providedCurrentTaskId',
	],

	props: {
		fileId: {
			type: Number,
			required: true,
		},
		isOutput: {
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
			// TODO, when we have task types with audio output, maybe switch to the assistant endpoint to get file with correct mimetype
			return this.isOutput
				? generateOcsUrl('taskprocessing/tasks/{taskId}/file/{fileId}?requesttoken={rToken}', {
					taskId: this.providedCurrentTaskId(),
					fileId: this.fileId,
					rToken: getRequestToken(),
				})
				: generateOcsUrl('apps/assistant/api/v1/file/{fileId}/display', { fileId: this.fileId })
		},
	},

	watch: {
	},

	mounted() {
		console.debug('CURRENT TASK', this.providedCurrentTaskId())
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
audio {
	border-radius: 16px;
}
</style>
