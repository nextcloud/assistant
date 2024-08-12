<template>
	<div ref="taskTypeSelect"
		class="task-type-select">
		<NcButton v-for="(t, i) in buttonTypes"
			:key="i + t.id"
			:type="getButtonType(t)"
			:title="t.description"
			@click="onTaskSelected(t)">
			{{ t.name }}
		</NcButton>
		<NcActions v-if="!onlyInline && actionTypes.length > 0"
			:force-menu="true"
			:container="$refs.taskTypeSelect">
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
		/**
		 * Number of inline elements
		 * All elements are inline if this prop is null
		 */
		inline: {
			type: [Number, null],
			default: null,
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
		onlyInline() {
			return this.inline === null
		},
		buttonTypes() {
			if (this.onlyInline) {
				return this.options
			}
			// extra button replaces the last one
			if (this.extraButtonType !== null) {
				const types = this.options.slice(0, this.inline - 1)
				types.push(this.extraButtonType)
				return types
			} else {
				return this.options.slice(0, this.inline)
			}
		},
		actionTypes() {
			if (this.extraButtonType !== null) {
				// the extra button replaces the last one so we need the last one as an action
				// take all non-inline options that are not selected and that are not the extra button
				const types = this.options.slice(this.inline).filter(t => t.id !== this.value && t.id !== this.extraButtonType.id)
				// add the one that was a button and that has been replaced
				if (this.extraButtonType.id !== this.options[this.inline - 1].id) {
					types.unshift(this.options[this.inline - 1])
				}
				return types
			} else {
				return this.options.slice(this.inline)
			}
		},
	},

	watch: {
		options() {
			this.moveSelectedIfInMenu()
		},
	},

	mounted() {
		this.moveSelectedIfInMenu()
	},

	methods: {
		moveSelectedIfInMenu() {
			if (this.onlyInline) {
				return
			}
			// if the initially selected value is in the dropdown, get it out
			const selectedAction = this.actionTypes.find(a => a.id === this.value)
			if (this.actionTypes.find(a => a.id === this.value)) {
				this.extraButtonType = selectedAction
			}
		},
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
