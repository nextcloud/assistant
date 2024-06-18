<template>
	<NcEmptyContent class="empty-view" :name="name" :description="description">
		<template #icon>
			<slot name="icon" />
		</template>
	</NcEmptyContent>
</template>

<script>
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'NoSession',

	components: {
		NcEmptyContent,
	},

	props: {
		name: {
			type: String,
			required: true,
		},
		description: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.empty-view {
	padding: calc(var(--default-grid-baseline) * 4);
}

.empty-content {
	margin: 0 !important;
}
</style>
