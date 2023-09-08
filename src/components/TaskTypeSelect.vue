<template>
	<div class="task-type-select">
		<NcButton v-for="(t, i) in buttonTypes"
			:key="i + t.id"
			:type="getButtonType(t)"
			@click="onTaskSelected(t)">
			{{ t.name }}
		</NcButton>
		<NcActions>
			<NcActionButton v-for="(t, i) in actionTypes"
				:key="i + t.id"
				class="no-icon-action"
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
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

const BUTTON_COUNT = 2

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
	},

	emits: [
		'update:value',
	],

	data() {
		return {
		}
	},

	computed: {
		buttonTypes() {
			const types = this.options.slice(0, BUTTON_COUNT)
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
			if (this.value !== null && types.find(t => t.id === this.value) === undefined) {
				const buttonToAdd = this.options.find(t => t.id === this.value)
				if (buttonToAdd) {
					types.push(buttonToAdd)
				}
			}
			return types
		},
		actionTypes() {
			return this.options.slice(BUTTON_COUNT)
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
