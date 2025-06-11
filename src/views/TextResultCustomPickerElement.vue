<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="mp" class="assistant-picker-content-wrapper" />
</template>

<script>
import checkSvg from '@mdi/svg/svg/check.svg?raw'

export default {
	name: 'TextResultCustomPickerElement',

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
	},

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
			appId: 'assistant',
			taskType: this.taskType,
			closeOnResult: false,
			actionButtons: [
				{
					label: t('assistant', 'Submit'),
					title: t('assistant', 'Submit the current task\'s result'),
					type: 'primary',
					iconSvg: checkSvg,
					onClick: (task) => {
						this.$emit('submit', task.output[this.outputKey] ?? '')
						this.$el.dispatchEvent(new CustomEvent('submit', { detail: task.output[this.outputKey] ?? '', bubbles: true }))
					},
				},
			],
			mountPoint: this.$refs.mp,
		}).catch(error => {
			console.debug('[assistant picker] assistant was closed', error)
			this.$emit('cancel')
		})
	},

	beforeDestroy() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
