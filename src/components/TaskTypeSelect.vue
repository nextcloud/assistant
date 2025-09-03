<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="taskTypeSelect"
		class="task-type-select">
		<template v-for="variants in buttonTypesByInlineStatus.inline">
			<NcActions v-if="hasSubMenu(variants)"
				:key="variants.id"
				:force-menu="true"
				:menu-name="variants.text"
				:container="$refs.taskTypeSelect"
				:primary="isCategorySelected(variants)"
				:class="{ categoryWithSubSelected: useModernStyle && isCategorySelected(variants) }"
				@click="onMenuCategorySelected(variants)">
				<NcActionButton v-for="t in variants.tasks"
					:key="t.id"
					:disabled="selectedTask(t)"
					:title="t.description"
					:close-after-click="true"
					@click="onTaskSelected(t)">
					<template #icon>
						<div style="width: 16px" />
					</template>
					{{ t.name }}
				</NcActionButton>
				<template #icon>
					<component :is="variants.icon" />
				</template>
			</NcActions>
			<NcButton v-else
				:key="variants.id + '-button'"
				:variant="isCategorySelected(variants) ? 'primary' : 'secondary'"
				:class="{ categorySelected: useModernStyle && isCategorySelected(variants) }"
				:title="variants.text"
				@click="onMenuCategorySelected(variants)">
				<template #icon>
					<component :is="variants.icon" />
				</template>
				{{ variants.text }}
			</NcButton>
		</template>
		<NcActions
			:force-menu="true"
			:container="$refs.taskTypeSelect"
			@close="categorySubmenu = null">
			<template v-if="!categorySubMenuTaskType">
				<NcActionButton v-for="variant in buttonTypesByInlineStatus.overflow"
					:key="variant.id"
					:is-menu="variant.tasks.length > 1 || variant.id === 'other'"
					:title="variant.text"
					@click="onMenuCategorySelected(variant)">
					<template #icon>
						<component :is="variant.icon" />
					</template>
					{{ variant.text }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionButton v-for="t in categorySubMenuTaskType.tasks"
					:key="t.id"
					:disabled="selectedTask(t)"
					:title="t.description"
					:close-after-click="true"
					@click="onTaskSelected(t)">
					<template #icon>
						<div style="width: 16px" />
					</template>
					{{ t.name }}
				</NcActionButton>
			</template>
		</NcActions>
	</div>
</template>

<script>
import MessageOutlineIcon from 'vue-material-design-icons/MessageOutline.vue'
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import TextLongIcon from 'vue-material-design-icons/TextLong.vue'
import ImageOutlineIcon from 'vue-material-design-icons/ImageOutline.vue'
import WebIcon from 'vue-material-design-icons/Web.vue'
import FileIcon from 'vue-material-design-icons/File.vue'

import ContentPasteSearchIcon from './icons/ContentPasteSearch.vue'
import WaveformIcon from './icons/Waveform.vue'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAssistantButton from '@nextcloud/vue/components/NcAssistantButton'

import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'TaskTypeSelect',

	components: {
		NcActions,
		NcActionButton,
		MessageOutlineIcon,
		NcButton,
		NcAssistantButton,
	},

	props: {
		modelValue: {
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
		'update:model-value',
	],

	data() {
		return {
			categorySubmenu: null,
			useModernStyle: loadState('assistant', 'use-modern-style', false),
		}
	},

	computed: {
		onlyInline() {
			return this.inline === null
		},
		buttonTypes() {
			const taskTypes = {}
			for (const task of this.options) {
				const type = this.getTaskCategory(task.id)
				if (!taskTypes[type]) {
					taskTypes[type] = []
				}
				taskTypes[type].push(task)
			}
			const result = []
			for (const entry of Object.entries(taskTypes)) {
				if (entry[0] === 'other') {
					continue
				}
				result.push({
					id: entry[0],
					text: this.getTextForCategory(entry[0]),
					icon: this.getCategoryIcon(entry[0]),
					tasks: entry[1],
				})
			}
			// Ensure the "other" category is always last
			if (taskTypes.other) {
				result.push({
					id: 'other',
					text: this.getTextForCategory('other'),
					icon: this.getCategoryIcon('other'),
					tasks: taskTypes.other,
				})
			}
			return result
		},
		buttonTypesByInlineStatus() {
			if (this.onlyInline) {
				return { inline: this.buttonTypes, overflow: [] }
			}
			const inlineButtonTypes = this.buttonTypes.slice(0, this.inline)
			let overflowButtonTypes = this.buttonTypes.slice(this.inline)

			// Ensure that the selection is never inline otherwise swap with the last uninlined category
			const selection = overflowButtonTypes.find(t => this.isCategorySelected(t))
			if (selection) {
				const removal = inlineButtonTypes.pop()
				inlineButtonTypes.push(selection)
				overflowButtonTypes = overflowButtonTypes.filter(t => t.id !== selection.id)
				if (removal) {
					overflowButtonTypes.unshift(removal)
				}
			}
			return { overflow: overflowButtonTypes, inline: inlineButtonTypes }
		},
		categorySubMenuTaskType() {
			return this.buttonTypesByInlineStatus.overflow.find(t => t.id === this.categorySubmenu)
		},
	},

	mounted() {
	},

	methods: {
		selectedTask(taskType) {
			return taskType.id === this.modelValue
		},
		isCategorySelected(category) {
			return category.id === this.getTaskCategory(this.modelValue || '')
		},
		onTaskSelected(taskType) {
			this.$emit('update:model-value', taskType.id)
		},
		hasSubMenu(taskType) {
			return taskType.tasks.length > 1 || taskType.id === 'other'
		},
		onMenuCategorySelected(taskType) {
			if (this.hasSubMenu(taskType)) {
				this.categorySubmenu = taskType.id
			} else {
				this.onTaskSelected(taskType.tasks[0])
				this.categorySubmenu = null
			}
		},
		getTaskCategory(id) {
			if (id.startsWith('chatty')) {
				return 'chat'
			} else if (id.startsWith('context_chat')) {
				return 'context'
			} else if (id.includes('translate')) {
				return 'translate'
			} else if (id.startsWith('richdocuments')) {
				return 'generate'
			} else if (id.includes('image') || id.includes('sticker')) {
				return 'image'
			} else if (id.includes('audio') || id.includes('speech')) {
				return 'audio'
			} else if (id.includes('text')) {
				return 'text'
			}
			return 'other'
		},
		getTextForCategory(category) {
			switch (category) {
			case 'chat':
				return t('assistant', 'Chat with AI')
			case 'context':
				return t('assistant', 'Context Chat')
			case 'text':
				return t('assistant', 'Work with text')
			case 'image':
				return t('assistant', 'Work with images')
			case 'translate':
				return t('assistant', 'Translate')
			case 'audio':
				return t('assistant', 'Work with audio')
			case 'generate':
				return t('assistant', 'Generate file')
			default:
				return t('assistant', 'Other')
			}
		},
		getCategoryIcon(category) {
			switch (category) {
			case 'chat':
				return MessageOutlineIcon
			case 'context':
				return ContentPasteSearchIcon
			case 'text':
				return TextLongIcon
			case 'image':
				return ImageOutlineIcon
			case 'translate':
				return WebIcon
			case 'audio':
				return WaveformIcon
			case 'generate':
				return FileIcon
			default:
				return DotsHorizontalIcon
			}
		},
	},
}
</script>

<style lang="scss">
.task-type-select {
	.categorySelected,
	.categoryWithSubSelected button {
		background: var(--color-element-assistant) !important;
		color: white !important;
		border: none !important;
		padding-block: 0 !important;
	}
}
</style>

<style scoped lang="scss">
.task-type-select {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	row-gap: 8px;
	column-gap: 6px;
}
</style>
