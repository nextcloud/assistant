<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="text-list-field">
		<h3 :title="field.description">
			{{ field.name }}
		</h3>
		<div class="text-list-field--items">
			<div v-for="(v, i) in arrayValue"
				:key="fieldKey + '-' + i"
				class="text-list--item">
				<TextInput
					:id="fieldKey + '-input' + '-' + i"
					class="text-input"
					:value="v ?? ''"
					:is-output="isOutput"
					:label="field.description"
					:placeholder="field.description"
					:title="field.name"
					@update:value="onItemValueChanged(i, $event)" />
				<NcButton v-if="!isOutput"
					class="delete-button"
					type="secondary"
					@click="onDeleteItem(i)">
					<template #icon>
						<DeleteIcon />
					</template>
				</NcButton>
			</div>
		</div>
		<NcButton v-if="!isOutput"
			class="more-button"
			type="secondary"
			@click="onAddItem">
			<template #icon>
				<PlusIcon />
			</template>
		</NcButton>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import DeleteIcon from '../icons/DeleteIcon.vue'

import TextInput from './TextInput.vue'

export default {
	name: 'ListOfTextsField',

	components: {
		TextInput,
		NcButton,
		PlusIcon,
		DeleteIcon,
	},

	props: {
		fieldKey: {
			type: String,
			required: true,
		},
		value: {
			type: [Array, null],
			default: null,
		},
		field: {
			type: Object,
			required: true,
		},
		isOutput: {
			type: Boolean,
			default: false,
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
		arrayValue() {
			return this.value ?? []
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onItemValueChanged(i, itemValue) {
			const newValue = this.arrayValue.slice()
			newValue[i] = itemValue?.trim()
			this.$emit('update:value', newValue)
			console.debug('[Assistant] on item value change', i, itemValue, newValue)
		},
		onDeleteItem(i) {
			if (this.arrayValue.length === 1 && i === 0) {
				this.$emit('update:value', [])
				return
			}
			const newValue = this.arrayValue.slice().splice(i - 1, 1)
			this.$emit('update:value', newValue)
			console.debug('delete', i)
		},
		onAddItem() {
			const newValue = [...this.arrayValue, '']
			this.$emit('update:value', newValue)
		},
	},
}
</script>

<style lang="scss">
.text-list-field {

	&--items {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}
}
</style>
