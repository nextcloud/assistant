<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcHeaderButton
		id="assistant"
		:aria-label="t('assistant', 'Nextcloud Assistant')"
		:description="t('assistant', 'Nextcloud Assistant header menu entry')"
		@click="$emit('click')">
		<template #icon>
			<AssistantIcon :size="20" />
		</template>
	</NcHeaderButton>
</template>

<script>
import AssistantIcon from './icons/AssistantIcon.vue'

import NcHeaderButton from '@nextcloud/vue/components/NcHeaderButton'

export default {
	name: 'AssistantHeaderMenuEntry',
	components: {
		AssistantIcon,
		NcHeaderButton,
	},
	emits: ['click'],
}
</script>
