<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<input ref="fileInput"
			type="file"
			:accept="fileInputAccept"
			:multiple="multiple"
			style="display: none;"
			@cancel.stop="onCancel"
			@change="onUploadFileSelected">
		<NcButton
			v-bind="$attrs"
			variant="secondary"
			@click="onUploadFile">
			<template #icon>
				<NcLoadingIcon v-if="isUploading" />
				<UploadOutlineIcon v-else />
			</template>
			{{ label }}
		</NcButton>
	</div>
</template>

<script>
import UploadOutlineIcon from 'vue-material-design-icons/UploadOutline.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'

import { showError } from '@nextcloud/dialogs'
import { uploadInputFile } from '../../utils.js'

export default {
	name: 'UploadInputFileButton',

	components: {
		NcButton,
		UploadOutlineIcon,
		NcLoadingIcon,
	},

	props: {
		label: {
			type: String,
			default: t('assistant', 'Upload file'),
		},
		accept: {
			type: Array,
			default: () => [],
		},
		multiple: {
			type: Boolean,
			default: false,
		},
		isUploading: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'files-uploaded',
		'update:is-uploading',
	],

	data() {
		return {
		}
	},

	computed: {
		fileInputAccept() {
			return this.accept.length > 0
				? this.accept.join(',')
				: undefined
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCancel() {
			console.debug('[assistant] file upload cancel')
		},
		onUploadFile() {
			this.$refs.fileInput.click()
		},
		onUploadFileSelected(e) {
			if (this.$refs.fileInput.files.length === 0) {
				return
			}
			const files = this.$refs.fileInput.files
			console.debug('FILES', this.$refs.fileInput.files)
			if (!this.multiple) {
				this.$emit('update:is-uploading', true)
				const file = files[0]
				uploadInputFile(file).then(response => {
					this.$emit('files-uploaded', response.data.ocs.data)
				}).catch(error => {
					showError(t('assistant', 'Could not upload the file'))
					console.error(error)
				}).then(() => {
					this.$emit('update:is-uploading', false)
				})
			} else {
				this.$emit('update:is-uploading', true)
				Promise.all(Array.from(files).map(f => uploadInputFile(f)))
					.then(responses => {
						if (responses.some(response => response.code === 'ERR_CANCELED')) {
							console.debug('At least one request has been canceled, do nothing')
							return
						}
						this.$emit('files-uploaded', responses.map(response => response.data.ocs.data))
					})
					.catch(error => {
						showError(t('assistant', 'Could not upload the files'))
						console.error(error)
					}).then(() => {
						this.$emit('update:is-uploading', false)
					})
			}
		},
	},
}
</script>

<style lang="scss">
// nothing yet
</style>
