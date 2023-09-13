<template>
	<div class="task-type-select">
		<NcButton v-for="(t, i) in buttonTypes"
			:key="i + t.id"
			:type="getButtonType(t)"
			@click="onTaskSelected(t)">
			{{ t.name }}
		</NcButton>
		<NcActions v-if="actionTypes.length > 0"
			:force-menu="true">
			<NcActionButton v-for="(t, i) in actionTypes"
				:key="i + t.id"
				class="no-icon-action"
				:aria-label="t.name"
				:close-after-click="true"
				@click="onMenuTaskSelected(t)">
				<template #icon>
					<div style="width: 16px" />
				</template>
				{{ t.name }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

export default {
	name: 'TaskTypeSelect',

	components: {
		NcButton,
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
			extraButtonType: null,
		}
	},

	computed: {
		buttonTypes() {
			const types = this.options.slice(0, this.inline)
			/*
			// TODO delete next line
			types.push(...[
				{
					id: 'plop1',
					name: 'DummyTask1',
				},
				{
					id: 'plop2',
					name: 'DummyTask2',
				},
				{
					id: 'plop3',
					name: 'DummyTask3',
				},
			])
			*/
			if (this.extraButtonType !== null) {
				types.push(this.extraButtonType)
			}
			return types
		},
		actionTypes() {
			return this.options.slice(this.inline)
		},
	},

	mounted() {
	},

	methods: {
		getButtonType(taskType) {
			return taskType.id === this.value
				? 'primary'
				: 'secondary'
		},
		onTaskSelected(taskType) {
			this.$emit('update:value', taskType.id)
		},
		onMenuTaskSelected(taskType) {
			this.extraButtonType = taskType
			this.onTaskSelected(taskType)
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
}
</style>
