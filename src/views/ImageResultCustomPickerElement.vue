<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="mp" class="assistant-picker-content-wrapper" />
</template>

<script>
import checkSvg from '@mdi/svg/svg/check.svg?raw'

import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ImageResultCustomPickerElement',

	components: {
	},

	props: {
		providerId: {
			type: String,
			required: true,
		},
		accessible: {
			type: Boolean,
			default: false,
		},
		taskType: {
			type: String,
			required: true,
		},
		outputKey: {
			type: String,
			required: true,
		},
		multipleImages: {
			type: Boolean,
			default: false,
		},
		inputs: {
			type: Object,
			default: () => ({}),
			required: false,
		},
	},

	emits: ['cancel', 'submit'],

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		OCA.Assistant.openAssistantForm({
			inputs: this.inputs,
			appId: 'assistant',
			taskType: this.taskType,
			closeOnResult: false,
			actionButtons: [
				{
					label: t('assistant', 'Submit'),
					title: t('assistant', 'Submit the current task\'s result'),
					variant: 'primary',
					iconSvg: checkSvg,
					onClick: (task) => {
						this.submit(task)
					},
				},
			],
			mountPoint: this.$refs.mp,
		}).catch(error => {
			console.debug('[assistant picker] assistant was closed', error)
			this.$emit('cancel')
			this.$el.dispatchEvent(new CustomEvent('cancel', { bubbles: true }))
		})
	},

	beforeUnmount() {
	},

	methods: {
		submit(task) {
			const fileIds = this.multipleImages
				? task.output[this.outputKey]
				: [task.output[this.outputKey]]
			Promise.all(fileIds.map(fid => this.shareFile(fid, task.id)))
				.then(responses => {
					if (responses.some(response => response.code === 'ERR_CANCELED')) {
						console.debug('At least one request has been canceled, do nothing')
						return
					}
					const shareLinks = responses.map(r => {
						const token = r.data.ocs.data.shareToken
						return window.location.protocol + '//' + window.location.host + generateUrl('/s/{token}', { token })
					})
					this.$emit('submit', shareLinks.join('\n\n'))
					this.$el.dispatchEvent(new CustomEvent('submit', { detail: shareLinks.join('\n\n'), bubbles: true }))
				})
		},
		shareFile(fileId, taskId) {
			const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/share', {
				taskId,
				fileId,
			})
			return axios.post(url)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
