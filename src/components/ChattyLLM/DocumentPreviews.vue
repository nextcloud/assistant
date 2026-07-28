<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="document-previews">
		<div class="document-previews__list">
			<div v-for="fileId in fileIds"
				:key="fileId"
				class="document-previews__item">
				<FileDisplay :file-id="fileId"
					:task-id="null" />
				<NcButton class="document-previews__delete"
					variant="tertiary"
					:aria-label="t('assistant', 'Remove this media')"
					@click="$emit('delete', fileId)">
					<template #icon>
						<TrashCanOutlineIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import FileDisplay from '../fields/FileDisplay.vue'

export default {
	name: 'DocumentPreviews',

	components: {
		FileDisplay,
		NcButton,
		TrashCanOutlineIcon,
	},

	props: {
		fileIds: {
			type: Array,
			required: true,
		},
	},

	emits: [
		'delete',
	],
}
</script>

<style lang="scss" scoped>
.document-previews {
	overflow-x: auto;

	&__list {
		display: flex;
		flex-direction: row;
		gap: 8px;
	}

	&__item {
		position: relative;
		padding: 8px;
		border-radius: var(--border-radius-large);
		background-color: var(--color-main-background);

		&:hover {
			background-color: var(--color-primary-element-light-hover);
		}
	}

	&__delete {
		position: absolute;
		right: 0;
		bottom: 0;
	}
}
</style>
