<template>
	<div class="task-type-select">
		<NcButton v-for="t in buttonTypes"
			:key="t.id"
			:type="getButtonType(t)"
			@click="onTaskSelected(t)">
			{{ t.name }}
		</NcButton>
		<NcActions>
			<NcActionButton v-for="t in actionTypes"
				:key="t.id"
				:aria-label="t.name"
				:close-after-click="true"
				@click="onTaskSelected(t)">
				<template #icon>
					<CircleSmallIcon />
				</template>
				{{ t.name }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import CircleSmallIcon from 'vue-material-design-icons/CircleSmall.vue'

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
		CircleSmallIcon,
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
