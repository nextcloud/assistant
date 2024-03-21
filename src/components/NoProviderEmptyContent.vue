<template>
	<NcEmptyContent
		:name="t('assistant', 'No provider found')"
		:description="t('assistant', 'AI Providers need to be installed to use the Assistant')">
		<template v-if="isAdmin" #action>
			<div class="actions">
				<span v-html="action1Html" />
				<span v-html="action2Html" />
			</div>
		</template>
		<template #icon>
			<AssistantIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import AssistantIcon from './icons/AssistantIcon.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const toolSectionUrl = generateUrl('/settings/apps/tools')
const toolLinkText = t('assistant', 'tool')
const toolLink = `<a class="external" target="_blank" href="${toolSectionUrl}">${toolLinkText}</a>`

const integrationSectionUrl = generateUrl('/settings/apps/integration')
const integrationLinkText = t('assistant', 'integration')
const integrationLink = `<a class="external" target="_blank" href="${integrationSectionUrl}">${integrationLinkText}</a>`

const aiDocUrl = 'https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html'
const aiDocLinkText = t('assistant', 'complete AI documentation')
const aiAdminDocLink = `<a class="external" target="_blank" href="${aiDocUrl}">${aiDocLinkText}</a>`

export default {
	name: 'NoProviderEmptyContent',

	components: {
		AssistantIcon,
		NcEmptyContent,
	},

	props: {
	},

	data() {
		return {
			isAdmin: getCurrentUser()?.isAdmin,
		}
	},

	computed: {
		action1Html() {
			return t('assistant', 'AI provider apps can be found in the {toolLink} and {integrationLink} app settings sections.', {
				toolLink,
				integrationLink,
			}, undefined, { escape: false, sanitize: false })
		},
		action2Html() {
			return t('assistant', 'You can also check the {aiAdminDocLink}', {
				aiAdminDocLink,
			}, undefined, { escape: false, sanitize: false })
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style lang="scss">
.actions {
	display: flex;
	flex-direction: column;
	align-items: center;
}
</style>
