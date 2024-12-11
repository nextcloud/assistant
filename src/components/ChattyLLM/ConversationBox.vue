<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="convo-box">
		<NoSession v-if="messages === null"
			:name="t('assistant', 'Error loading messages')"
			description="Please try again later.">
			<template #icon>
				<AlertOutlineIcon />
			</template>
		</NoSession>
		<NoSession v-else-if="loading.initialMessages"
			:name="t('assistant', 'Loading messages…')"
			description="">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NoSession>
		<Message v-for="(message, idx) in messages"
			v-else
			:id="'message' + idx"
			:key="'message' + idx"
			:class="{ 'convo-box__message--dim': regenerateFromId && regenerateFromId <= message.id }"
			:message="message"
			:show-regenerate="message.role === 'assistant' && idx === (messages.length - 1)"
			:delete-loading="loading.messageDelete && message.id === deleteMessageId"
			:regenerate-loading="loading.llmGeneration && message.id === regenerateFromId"
			:new-message-loading="loading.newHumanMessage && idx === (messages.length - 1)"
			@regenerate="regenerate(message.id)"
			@delete="deleteMessage(message.id)" />
	</div>
</template>

<script>
import AlertOutlineIcon from 'vue-material-design-icons/AlertOutline.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import Message from './Message.vue'
import NoSession from './NoSession.vue'

export default {
	name: 'ConversationBox',

	components: {
		AlertOutlineIcon,

		NcLoadingIcon,

		Message,
		NoSession,
	},

	props: {
		// [{ id: number, session_id: number, role: string, content: string, timestamp: number }]
		messages: {
			type: Array,
			default: null,
		},
		loading: {
			type: Object,
			default: () => ({
				initialMessages: false,
				olderMessages: false,
				llmGeneration: false,
				titleGeneration: false,
				newHumanMessage: false,
				newSession: false,
				messageDelete: false,
				sessionDelete: false,
			}),
		},
	},

	data: () => {
		return {
			regenerateFromId: null,
			deleteMessageId: null,
		}
	},

	watch: {
		'loading.messageDelete'() {
			if (!this.loading.messageDelete) {
				this.deleteMessageId = null
			}
		},
		'loading.llmGeneration'() {
			if (!this.loading.llmGeneration) {
				this.regenerateFromId = null
			}
		},
	},

	methods: {
		deleteMessage(messageId) {
			console.debug('Convo box deleteMessage id:', messageId)
			this.deleteMessageId = messageId
			this.$emit('delete', messageId)
		},
		regenerate(messageId) {
			this.regenerateFromId = messageId
			this.$emit('regenerate', messageId)
		},
	},
}
</script>

<style lang="scss" scoped>
.convo-box {
	display: flex;
	flex-direction: column;
	gap: 0.5em;
	height: 100%;

	&__message--dim {
		opacity: 0.5;
	}
}
</style>
