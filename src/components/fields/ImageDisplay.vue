<template>
	<div class="image-display">
		<img :src="imageUrl">
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
	},

	emits: [
		'delete',
	],

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
		border-radius: var(--border-radius-large);
	}
}
</style>
