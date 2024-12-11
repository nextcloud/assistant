<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="message-actions">
		<NcButton
			type="tertiary"
			:aria-label="t('assistant', 'Copy message')"
			:title="t('assistant', 'Copy message')"
			@click="$emit('copy', $event)">
			<template #icon>
				<CopyIcon :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="showRegenerate"
			type="tertiary"
			:aria-label="t('assistant', 'Regenerate message')"
			:title="t('assistant', 'Regenerate message')"
			:disabled="regenerateLoading"
			@click="$emit('regenerate', $event)">
			<template v-if="regenerateLoading" #icon>
				<NcLoadingIcon :size="20" />
			</template>
			<template v-else #icon>
				<ReloadIcon :size="20" />
			</template>
		</NcButton>
		<NcButton
			type="tertiary"
			:aria-label="t('assistant', 'Delete message')"
			:title="t('assistant', 'Delete message')"
			:disabled="regenerateLoading"
			@click="$emit('delete', $event)">
			<template v-if="deleteLoading" #icon>
				<NcLoadingIcon :size="20" />
			</template>
			<template v-else #icon>
				<DeleteIcon :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import ReloadIcon from 'vue-material-design-icons/Reload.vue'

import CopyIcon from '../icons/CopyIcon.vue'
import DeleteIcon from '../icons/DeleteIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

export default {
	name: 'MessageActions',

	components: {
		CopyIcon,
		DeleteIcon,
		ReloadIcon,

		NcButton,
		NcLoadingIcon,
	},

	props: {
		showRegenerate: {
			type: Boolean,
			default: false,
		},
		regenerateLoading: {
			type: Boolean,
			default: false,
		},
		deleteLoading: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style lang="scss" scoped>
.message-actions {
	display: flex;
	right: 0.5em;
	top: 0.5em;
	position: absolute;
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-element);
	box-shadow: 0 0 4px 0 var(--color-box-shadow);
	height: var(--default-clickable-area);
	z-index: 1;
	float: right;
}
</style>
