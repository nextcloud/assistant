<template>
	<div>
		<input ref="fileInput"
			type="file"
			:accept="fileInputAccept"
			:multiple="multiple"
			style="display: none;"
			@change="onUploadFileSelected">
		<NcButton
			v-bind="$attrs"
			type="secondary"
			@click="onUploadFile">
			<template #icon>
				<NcLoadingIcon v-if="isUploading" />
				<UploadIcon v-else />
			</template>
			{{ label }}
		</NcButton>
	</div>
</template>

<script>
import UploadIcon from 'vue-material-design-icons/Upload.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

const uploadEndpointUrl = generateOcsUrl('/apps/assistant/api/v1/input-file')

export default {
	name: 'UploadInputFileButton',

	components: {
		NcButton,
		UploadIcon,
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
				this.uploadFile(file).then(response => {
					this.$emit('files-uploaded', response.data.ocs.data)
				}).catch(error => {
					showError(t('assistant', 'Could not upload the file'))
					console.error(error)
				}).then(() => {
					this.$emit('update:is-uploading', false)
				})
			} else {
				this.$emit('update:is-uploading', true)
				Promise.all(Array.from(files).map(f => this.uploadFile(f)))
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
		uploadFile(file) {
			const formData = new FormData()
			formData.append('data', file)
			formData.append('filename', file.name)
			return axios.post(uploadEndpointUrl, formData)
		},
	},
}
</script>

<style lang="scss">
// nothing yet
</style>
