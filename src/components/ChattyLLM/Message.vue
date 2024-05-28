<template>
	<div class="message"
		@mouseover="showMessageActions = true"
		@mouseleave="showMessageActions = false">
		<MessageActions v-show="showMessageActions"
			class="message__actions"
			:show-regenerate="message.role === 'assistant'"
			:delete-loading="deleteLoading"
			:regenerate-loading="regenerateLoading"
			@copy="copyMessage(message.content)"
			@regenerate="$emit('regenerate')"
			@delete="$emit('delete')" />
		<div class="message__header">
			<div class="message__header__role">
				<NcAvatar
					:user="message.role === 'human' ? userId : 'Nextcloud Assistant'"
					:display-name="message.role === 'human' ? displayName : t('assistant', 'Nextcloud Assistant')"
					:is-no-user="message.role === 'assistant'"
					:show-user-status="false">
					<template v-if="message.role === 'human' && newMessageLoading" #icon>
						<NcLoadingIcon :size="20" />
					</template>
					<template v-else-if="message.role === 'assistant'" #icon>
						<AssistantIcon :size="20" />
					</template>
				</NcAvatar>
				<div class="message__header__role__name">
					{{ message.role === 'human' ? displayName : t('assistant', 'Nextcloud Assistant') }}
				</div>
			</div>
			<NcDateTime class="message__header__timestamp" :timestamp="new Date((message?.timestamp ?? 0) * 1000)" :ignore-seconds="true" />
		</div>
		<NcRichText class="message__content"
			:text="message.content"
			:use-markdown="true"
			:reference-limit="1"
			:autolink="true" />
	</div>
</template>

<script>
import AssistantIcon from '../icons/AssistantIcon.vue'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcDateTime from '@nextcloud/vue/dist/Components/NcDateTime.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'

import MessageActions from './MessageActions.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'Message',

	components: {
		AssistantIcon,

		NcAvatar,
		NcDateTime,
		NcLoadingIcon,
		NcRichText,

		MessageActions,
	},

	props: {
		// { id: number, session_id: number, role: string, content: string, timestamp: number }
		message: {
			type: Object,
			required: true,
		},
		deleteLoading: {
			type: Boolean,
			default: false,
		},
		regenerateLoading: {
			type: Boolean,
			default: false,
		},
		newMessageLoading: {
			type: Boolean,
			default: false,
		},
	},

	data: () => {
		return {
			displayName: getCurrentUser()?.displayName ?? getCurrentUser()?.uid ?? t('assistant', 'You'),
			userId: getCurrentUser()?.uid ?? t('assistant', 'yooniquely-you'),
			showMessageActions: false,
		}
	},

	methods: {
		copyMessage(message) {
			navigator.clipboard.writeText(message)
			showSuccess(t('assistant', 'Message copied to clipboard'))
		},
	},
}
</script>

<style lang="scss" scoped>
.message {
	border-radius: var(--border-radius-large);
	padding: 0.5em;
	position: relative;

	&:hover {
		background-color: var(--color-background-hover);
	}

	&__header {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: space-between;

		&__role {
			display: flex;
			flex-direction: row;
			align-items: center;
			gap: 0.5em;

			&__name {
				font-weight: bold;
			}
		}

		&__timestamp {
			color: var(--color-loading-light);
			font-size: 0.8em;
		}
	}

	&__content {
		margin-left: 2.6em;
		overflow: auto;

		:deep ol {
			margin-left: 1em;
		}
	}
}
</style>
