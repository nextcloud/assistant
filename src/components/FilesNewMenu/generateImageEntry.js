/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineAsyncComponent } from 'vue'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { translate as t } from '@nextcloud/l10n'
import Creation from '@mdi/svg/svg/creation.svg?raw'

const GenerateImageDialog = defineAsyncComponent(() => import('./GenerateImageDialog.vue'))

export const EntryId = 'assistant-generate-image'

export const entry = {
	id: EntryId,
	displayName: t('assistant', 'Generate image using AI'),
	iconSvgInline: Creation,
	order: 100,
	enabled() {
		return true
	},
	async handler(context, content) {
		await spawnDialog(GenerateImageDialog, {
			context,
			content,
		})
	},
}
