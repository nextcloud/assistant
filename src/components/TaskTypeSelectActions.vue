<template>
	<div class="task-type-select">
		<NcActions
			:force-name="true"
			:inline="inline">
			<NcActionButton v-for="(t, i) in options"
				:key="i + t.id"
				class="no-icon-action"
				:class="{ selected: t.id === value }"
				:aria-label="t.name"
				:close-after-click="true"
				@click="onTaskSelected(t)">
				<template #icon>
					<div style="width: 16px" />
				</template>
				{{ t.name }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

export default {
	name: 'TaskTypeSelectActions',

	components: {
		NcActions,
		NcActionButton,
	},

	props: {
		value: {
			type: [String, null],
			default: null,
		},
		options: {
			type: Array,
			required: true,
		},
		inline: {
			type: Number,
			default: 3,
		},
	},

	emits: [
		'update:value',
	],

	data() {
		return {
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
		onTaskSelected(taskType) {
			this.$emit('update:value', taskType.id)
		},
	},
}
</script>

<style lang="scss">
.task-type-select {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	row-gap: 8px;
	column-gap: 6px;

	.button-vue__icon {
		width: 16px !important;
		min-width: 16px !important;
	}
}
</style>
