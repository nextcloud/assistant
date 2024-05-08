<template>
	<div class="container">
		<NcAppNavigation>
			<NcAppNavigationList>
				<NcAppNavigationNew :text="t('assistant', 'New session')"
					type="secondary"
					@click="onNewSession">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
				</NcAppNavigationNew>
				<div v-if="sessions == null" class="unloaded-sessions">
					<NcLoadingIcon :size="30" />
					{{ t('assistant', 'Loading sessions...') }}
				</div>
				<div v-else-if="sessions != null && sessions.length === 0" class="unloaded-sessions">
					{{ t('assistant', 'No sessions yet') }}
				</div>
				<NcAppNavigationItem
					v-for="session in sessions"
					v-else
					:key="'session' + session.id"
					:active="session.id === active?.id"
					:name="getSessionTitle(session)"
					:title="getSessionTitle(session)"
					:aria-description="getSessionTitle(session)"
					:editable="true"
					:edit-label="t('assistant', 'Edit Title')"
					@click="onSessionSelect(session)"
					@update:name="(newTitle) => onEditSessionTitle(session.id, newTitle)">
					<template #actions>
						<NcActionButton @click="onGenerateSessionTitle(session.id)">
							<template v-if="!loading.titleGeneration" #icon>
								<AutoFixIcon :size="20" />
							</template>
							<template v-else #icon>
								<NcLoadingIcon :size="20" />
							</template>
							{{ t('assistant', 'Generate Title') }}
						</NcActionButton>
						<NcActionButton @click="deleteSession(session.id)">
							<template v-if="!loading.sessionDelete" #icon>
								<DeleteIcon :size="20" />
							</template>
							<template v-else #icon>
								<NcLoadingIcon :size="20" />
							</template>
							{{ t('assistant', 'Delete') }}
						</NcActionButton>
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</NcAppNavigation>
		<NcAppContent class="session-area">
			<div class="session-area__top-bar">
				{{ getSessionTitle(active) }}
			</div>
			<div class="session-area__chat-area">
				<NoSession v-if="loading.newSession"
					:name="t('assistant', 'Creating a new session')"
					description="">
					<template #icon>
						<NcLoadingIcon />
					</template>
				</NoSession>
				<NoSession v-else-if="active == null || (!loading.initialMessages && (messages?.length ?? 0) === 0)"
					:name="t('assistant', 'Hello there! What can I help you with today?')"
					:description="t('assistant', 'Try sending a message to spark a conversation.')">
					<template #icon>
						<AssistantIcon />
					</template>
				</NoSession>
				<div v-else class="session-area__chat-area__active-session">
					<div v-if="messages != null && messages.length > 0 && !allMessagesLoaded" class="session-area__chat-area__active-session__utility-button">
						<NcButton
							:aria-label="t('assistant', 'Load older messages')"
							:disabled="loading.initialMessages || loading.olderMessages"
							type="secondary"
							@click="onLoadOlderMessages">
							<template v-if="loading.olderMessages">
								<NcLoadingIcon />
							</template>
							<template v-else>
								{{ t('assistant', 'Load older messages') }}
							</template>
						</NcButton>
					</div>
					<ConversationBox :messages="messages"
						:loading="loading"
						@regenerate="regenerateLLMResponse"
						@delete="deleteMessage" />
					<div v-if="messages != null && messages.length > 0 && !loading.llmGeneration && !loading.newHumanMessage && messages[messages.length - 1]?.role === 'human'" class="session-area__chat-area__active-session__utility-button">
						<NcButton
							:aria-label="t('assistant', 'Retry response generation')"
							:disabled="loading.initialMessages || loading.llmGeneration"
							type="secondary"
							@click="getLLMResponse">
							{{ t('assistant', 'Retry response generation') }}
						</NcButton>
					</div>
				</div>
			</div>
			<InputArea ref="inputComponent"
				class="session-area__input-area"
				:chat-content.sync="chatContent"
				:loading="loading"
				@submit="handleSubmit" />
		</NcAppContent>
	</div>
</template>

<script>
import AutoFixIcon from 'vue-material-design-icons/AutoFix.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import AssistantIcon from '../icons/AssistantIcon.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationList from '@nextcloud/vue/dist/Components/NcAppNavigationList.js'
import NcAppNavigationNew from '@nextcloud/vue/dist/Components/NcAppNavigationNew.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import ConversationBox from './ConversationBox.vue'
import InputArea from './InputArea.vue'
import NoSession from './NoSession.vue'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

// future: type (text, image, file, etc), attachments, etc support

const getChatURL = (endpoint) => generateUrl('/apps/assistant/chat' + endpoint)
const Roles = {
	HUMAN: 'human',
	ASSISTANT: 'assistant',
}

export default {
	name: 'ChattyLLMInputForm',

	components: {
		AutoFixIcon,
		DeleteIcon,
		PlusIcon,

		AssistantIcon,

		NcActionButton,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationList,
		NcAppNavigationNew,
		NcButton,
		NcLoadingIcon,

		ConversationBox,
		InputArea,
		NoSession,
	},

	data: () => {
		return {
			// { id: number, title: string, user_id: string, timestamp: number }
			active: null,
			chatContent: '',
			sessions: null,
			// [{ id: number, session_id: number, role: string, content: string, timestamp: number }]
			messages: [], // null when failed to fetch
			messagesAxiosController: null, // for request cancellation
			allMessagesLoaded: false,
			loading: {
				initialMessages: false,
				olderMessages: false,
				llmGeneration: false,
				titleGeneration: false,
				newHumanMessage: false,
				newSession: false,
				messageDelete: false,
				sessionDelete: false,
			},
			msgCursor: 0,
			msgLimit: 20,
		}
	},

	watch: {
		async active() {
			this.allMessagesLoaded = false
			this.chatContent = ''
			this.msgCursor = 0
			this.messages = []
			this.$refs.inputComponent.focus()

			if (this.active != null) {
				await this.fetchMessages()
				this.scrollToBottom()
			}
		},
	},

	mounted() {
		this.fetchSessions()
	},

	methods: {
		scrollToBottom() {
			console.debug('scrollToBottom: active:', this.active)
			if (this.active == null) {
				return
			}
			if (this.messages == null) {
				return
			}

			this.$nextTick(() => {
				const lastIdx = this.messages.length - 1
				document.querySelector('#message' + lastIdx)?.scrollIntoView()
				this.$refs.inputComponent.focus()
			})
		},

		onNewSession() {
			this.active = null
			this.chatContent = ''
			this.$refs.inputComponent.focus()
		},

		onSessionSelect(session) {
			this.active = session
		},

		onEditSessionTitle(sessionId, newTitle) {
			console.debug(sessionId, newTitle)
			for (const session of this.sessions) {
				if (session.id === sessionId) {
					session.title = newTitle
					this.updateTitle(sessionId, newTitle)
					break
				}
			}
		},

		/**
		 * @param {{ id: number, title: string, user_id: string, timestamp: number }} session Chat session
		 */
		getSessionTitle(session) {
			if (session == null) {
				return ''
			}

			if (session.title?.trim()) {
				return session.title.length > 140 ? session.title.slice(0, 140) + '...' : session.title
			}

			return t('assistant', 'Session') + (session.timestamp ? (' ' + new Date(session.timestamp * 1000).toLocaleString()) : '')
		},

		async handleSubmit(event) {
			if (this.chatContent.trim() === '') {
				console.debug('empty message')
				return
			}

			console.debug('submit:', event)
			const role = Roles.HUMAN
			const content = this.chatContent.trim()
			const timestamp = +new Date() / 1000 | 0

			this.messages.push({ role, content, timestamp })
			this.chatContent = ''
			this.scrollToBottom()

			if (this.active != null) {
				// existing session
				await this.newMessage(role, content, timestamp)
			} else {
				await this.newSession(content, timestamp)
			}
		},

		onLoadOlderMessages() {
			if (this.loading.initialMessages || this.loading.olderMessages || this.allMessagesLoaded) {
				return
			}
			this.msgCursor += this.msgLimit
			this.fetchMessages(true)
		},

		async updateTitle(sessionId, title) {
			try {
				await axios.patch(getChatURL('/update_session'), {
					sessionId,
					title,
				})
			} catch (error) {
				console.error('updateTitle error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error updating session title'))
			}
		},

		async onGenerateSessionTitle(sessionId) {
			try {
				this.loading.titleGeneration = true
				const response = await axios.get(getChatURL('/generate_title'), { params: { sessionId } })
				if (response?.data?.result == null) {
					throw new Error('No title generated')
				}

				for (const session of this.sessions) {
					if (session.id === sessionId) {
						session.title = response?.data?.result
						break
					}
				}
			} catch (error) {
				console.error('onGenerateSessionTitle error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error generating a title'))
			} finally {
				this.loading.titleGeneration = false
			}
		},

		async deleteSession(sessionId) {
			try {
				this.loading.sessionDelete = true
				await axios.delete(getChatURL('/delete_session'), {
					params: { sessionId },
				})
				this.sessions = this.sessions.filter((session) => session.id !== sessionId)
				if (this.active?.id === sessionId) {
					this.onNewSession()
				}
			} catch (error) {
				console.error('deleteSession error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error deleting session'))
			} finally {
				this.loading.sessionDelete = false
			}
		},

		async fetchSessions() {
			try {
				const response = await axios.get(getChatURL('/sessions'))
				console.debug('fetchSessions response:', response)
				this.sessions = response.data
			} catch (error) {
				this.sessions = []
				console.error('fetchSessions error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error fetching sessions'))
			}
		},

		async deleteMessage(messageId) {
			try {
				this.loading.messageDelete = true
				await axios.delete(getChatURL('/delete_message'), {
					params: { messageId },
				})
				this.messages = this.messages.filter((message) => message.id !== messageId)
			} catch (error) {
				console.error('deleteMessage error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error deleting message'))
			} finally {
				this.loading.messageDelete = false
			}
		},

		async fetchMessages(older = false) {
			if (this.active == null) {
				console.debug('no active session')
				return
			}

			try {
				console.debug('fetching messages for:', this.active)
				if (older) {
					this.loading.olderMessages = true
				} else {
					this.loading.initialMessages = true
				}

				if (this.messagesAxiosController != null) {
					this.messagesAxiosController.abort()
				}
				this.messagesAxiosController = new AbortController()

				const response = await axios.get(getChatURL('/messages'), {
					params: {
						sessionId: this.active.id,
						cursor: this.msgCursor,
						limit: this.msgLimit,
					},
					signal: this.messagesAxiosController.signal,
				})
				console.debug('fetchMessages response:', response.data)
				if (this.messages == null) {
					this.messages = []
				}
				this.messages.unshift(...response.data)

				if (response.data.length < this.msgLimit) {
					this.allMessagesLoaded = true
				}

				// not in the finally block to prevent overwrite of the loading state
				// by the finally block of the (async-ly) cancelled request
				// same for the messagesAxiosController
				this.loading.olderMessages = false
				this.loading.initialMessages = false
				this.messagesAxiosController = null
			} catch (error) {
				if (axios.isCancel(error)) {
					console.debug('fetchMessages cancelled')
					return
				}

				this.loading.initialMessages = false
				this.loading.olderMessages = false
				this.messages = null
				this.messagesAxiosController = null
				console.error('fetchMessages error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error fetching messages'))
			}
		},

		async newMessage(role, content, timestamp) {
			try {
				this.loading.newHumanMessage = true
				const response = await axios.put(getChatURL('/new_message'), {
					sessionId: this.active.id,
					role,
					content,
					timestamp,
				})
				console.debug('newMessage response:', response)
				this.loading.newHumanMessage = false

				// replace the last message with the response that contains the id
				this.messages[this.messages.length - 1] = response.data
				await this.getLLMResponse()
			} catch (error) {
				this.loading.newHumanMessage = false
				console.error('newMessage error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error creating a new message'))
			}
		},

		async newSession(content, timestamp, title = null) {
			try {
				this.loading.newHumanMessage = true
				this.loading.newSession = true
				const response = await axios.put(getChatURL('/new_session'), {
					content,
					timestamp,
					title,
				})
				console.debug('newSession response:', response)
				this.loading.newHumanMessage = false
				this.loading.newSession = false
				this.active = response.data?.session ?? null
				if (this.active == null) {
					throw new Error(t('assistant', 'Received malformed response for new session'))
				}

				this.sessions.unshift(this.active)

				// replace the last message with the response that contains the id
				this.messages = [response.data.message]
				await this.getLLMResponse()
			} catch (error) {
				this.loading.newHumanMessage = false
				this.loading.newSession = false
				console.error('newSession error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error creating a new session'))
			}
		},

		async getLLMResponse() {
			try {
				this.loading.llmGeneration = true
				const response = await axios.get(getChatURL('/generate'), { params: { sessionId: this.active.id } })
				console.debug('getLLMResponse response:', response)
				this.messages.push(response.data)
				this.scrollToBottom()
			} catch (error) {
				console.error('getLLMResponse error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error generating a response'))
			} finally {
				this.loading.llmGeneration = false
			}
		},

		async regenerateLLMResponse(messageId) {
			try {
				this.loading.llmGeneration = true
				const response = await axios.get(getChatURL('/regenerate'), { params: { messageId, sessionId: this.active.id } })
				console.debug('regenerateLLMResponse response:', response)
				this.messages = [...this.messages.filter((message) => message.id < messageId), response.data]
				this.scrollToBottom()
			} catch (error) {
				console.error('regenerateLLMResponse error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error regenerating a response'))
			} finally {
				this.loading.llmGeneration = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	height: 44em;
	margin-top: 8px;

	:deep .app-navigation-new {
		padding: 0.5em 0 !important;

		> button {
			border: 2px solid var(--color-primary-element-light-text);
			border-radius: var(--border-radius-pill);
			box-sizing: border-box;
			height: var(--default-clickable-area);
		}
	}

	.unloaded-sessions {
		display: flex;
		justify-content: center;
		align-items: center;
		gap: 1em;
		font-weight: bold;
		padding: 1em;
		height: 100%;
	}

	:deep .app-navigation {
		--app-navigation-max-width: calc(100vw - (var(--app-navigation-padding) + 24px + var(--default-grid-baseline)));
		background-color: var(--color-primary-element-light);
		color: var(--color-primary-element-light-text);
		border-radius: var(--border-radius-large);

		@media only screen and (max-width: 1024px) {
			position: relative !important;
		}

		button.app-navigation-toggle {
			border: 1px solid var(--color-border);
		}

		&--close ~ .session-area {
			.session-area__chat-area, .session-area__input-area {
				padding-left: 0 !important;
			}
		}
	}

	:deep .app-navigation-list {
		padding: 0.4em !important;
		box-sizing: border-box;
		height: 100%;

		.app-navigation-input-confirm > form {
			align-items: center;
			height: var(--default-clickable-area);

			> button {
				scale: calc(36/44);
			}
		}

		.app-navigation-entry-wrapper .app-navigation-entry-link {
			.app-navigation-entry-icon {
				display: none;
			}
			.app-navigation-entry__name {
				margin-left: 16px;
			}
		}

		.app-navigation-entry {
			&-link {
				padding-right: 0.3em;
			}

			&.active {
				font-weight: bold;

				&:hover {
					background-color: var(--color-primary-element) !important;
				}
			}

			&:hover {
				background-color: var(--color-primary-element-light-hover);
			}

			.app-navigation-entry-button {
				border: none !important;
				padding-right: 0 !important;

				> span {
					font-size: 100% !important;
					padding-left: 0;
				}
			}

			.editingContainer {
				margin: 0 !important;
				width: 100% !important;
				padding-left: 24px;
			}
		}
	}

	.session-area {
		display: flex;
		flex-direction: column;
		justify-content: space-between;

		&__top-bar {
			display: flex;
			align-items: center;
			position: sticky;
			top: 0;
			height: 60px;
			box-sizing: border-box;
			border-bottom: 1px solid var(--color-border);
			padding-left: 4.5em;
			font-weight: bold;
			background-color: var(--color-main-background);
			z-index: 99999;
		}

		&__chat-area {
			flex: 1;
			overflow-y: auto;
			padding: 1em;

			&__active-session__utility-button {
				display: flex;
				justify-content: center;
				padding: 1em;
			}
		}

		&__chat-area, &__input-area {
			padding-left: 1em;
		}

		&__input-area {
			position: sticky;
			bottom: 0;
		}
	}
}
</style>
