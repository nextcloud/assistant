<template>
	<NcButton
		v-bind="$attrs"
		type="secondary"
		@click="onButtonClick">
		<template #icon>
			<FolderPlusIcon />
		</template>
		{{ label }}
	</NcButton>
</template>

<script>
import FolderPlusIcon from 'vue-material-design-icons/FolderPlus.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'

export default {
	name: 'ChooseInputFileButton',

	components: {
		NcButton,
		FolderPlusIcon,
	},

	props: {
		label: {
			type: String,
			default: t('assistant', 'Choose file'),
		},
		pickerTitle: {
			type: String,
			default: t('assistant', 'Choose a file'),
		},
		accept: {
			type: Array,
			default: () => [],
		},
		multiple: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'files-chosen',
	],

	data() {
		return {
			picker: (callback) => getFilePickerBuilder(this.pickerTitle)
				.setMimeTypeFilter(this.accept)
				.setMultiSelect(this.multiple)
				.allowDirectories(false)
				.addButton({
					id: 'choose-input-file',
					label: t('assistant', 'Choose'),
					type: 'primary',
					callback: callback(),
				})
				.build(),
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		async onButtonClick() {
			await this.picker(this.pickerSubmitted).pick()
		},
		pickerSubmitted() {
			return (nodes) => {
				if (!nodes || nodes.length === 0 || !nodes[0].path) {
					showError(t('assistant', 'No file selected'))
					return
				}
				console.debug('aaaaaaaaaaaaaaaaa NODE', nodes[0])
				this.$emit('files-chosen', this.multiple ? nodes : nodes[0])
			}
		},
	},
}
</script>

<style lang="scss">
// nothing yet
</style>
