<template>
	<div class="container">
		<NcAppNavigation>
			<NcAppNavigationList>
				<NcAppNavigationNew :text="t('assistant', 'New conversation')"
					type="secondary"
					@click="newSession">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
				</NcAppNavigationNew>
				<div v-if="sessions == null" class="unloaded-sessions">
					<NcLoadingIcon :size="30" />
					{{ t('assistant', 'Loading conversations...') }}
				</div>
				<div v-else-if="sessions != null && sessions.length === 0" class="unloaded-sessions">
					{{ t('assistant', 'No conversations yet') }}
				</div>
				<NcAppNavigationItem
					v-for="session in sessions"
					v-else
					:key="'conversation' + session.id"
					:active="session.id === active?.id"
					:name="getSessionTitle(session)"
					:title="getSessionTitle(session)"
					:aria-description="getSessionTitle(session)"
					:editable="false"
					:inline-actions="1"
					@click="onSessionSelect(session)">
					<template #actions>
						<NcActionButton @click="deleteSession(session.id)">
							<template #icon>
								<DeleteIcon v-if="!loading.sessionDelete" :size="20" />
								<NcLoadingIcon v-else :size="20" />
							</template>
							{{ t('assistant', 'Delete') }}
						</NcActionButton>
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</NcAppNavigation>
		<NcAppContent class="session-area">
			<div class="session-area__top-bar">
				<div class="session-area__top-bar__title">
					<EditableTextField v-if="active != null"
						:initial-text="getSessionTitle(active)"
						:editing.sync="editingTitle"
						:placeholder="t('assistant', 'Conversation title')"
						:loading="loading.updateTitle"
						:max-length="100"
						@submit-text="onEditSessionTitle" />
				</div>
				<div v-if="active != null" class="session-area__top-bar__actions">
					<NcActions :open.sync="titleActionsOpen">
						<NcActionButton :disabled="loading.titleGeneration || editingTitle" @click="onEditSessionTitleClick">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('assistant', 'Edit Title') }}
						</NcActionButton>
						<NcActionButton :disabled="loading.titleGeneration || editingTitle" @click="onGenerateSessionTitle">
							<template #icon>
								<AutoFixIcon v-if="!loading.titleGeneration" :size="20" />
								<NcLoadingIcon v-else :size="20" />
							</template>
							{{ t('assistant', 'Generate Title') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
			<div class="session-area__chat-area">
				<NoSession v-if="loading.newSession"
					:name="t('assistant', 'Creating a new conversation')"
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
				<div v-else
					class="session-area__chat-area__active-session"
					:style="{ height: (loading.initialMessages || loading.newSession) ? '100%' : 'auto' }">
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
						@regenerate="runRegenerationTask"
						@delete="deleteMessage" />
					<div v-if="messages != null && messages.length > 0 && !loading.llmGeneration && !loading.newHumanMessage && messages[messages.length - 1]?.role === 'human'" class="session-area__chat-area__active-session__utility-button">
						<NcButton
							:aria-label="t('assistant', 'Retry response generation')"
							:disabled="loading.initialMessages || loading.llmGeneration"
							type="secondary"
							@click="runGenerationTask">
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
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import AssistantIcon from '../icons/AssistantIcon.vue'
import DeleteIcon from '../icons/DeleteIcon.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationList from '@nextcloud/vue/dist/Components/NcAppNavigationList.js'
import NcAppNavigationNew from '@nextcloud/vue/dist/Components/NcAppNavigationNew.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import ConversationBox from './ConversationBox.vue'
import EditableTextField from './EditableTextField.vue'
import InputArea from './InputArea.vue'
import NoSession from './NoSession.vue'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import moment from 'moment'

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
		PencilIcon,
		PlusIcon,

		AssistantIcon,

		NcActionButton,
		NcActions,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationList,
		NcAppNavigationNew,
		NcButton,
		NcLoadingIcon,

		ConversationBox,
		EditableTextField,
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
				updateTitle: false,
				newHumanMessage: false,
				newSession: false,
				messageDelete: false,
				sessionDelete: false,
			},
			msgCursor: 0,
			msgLimit: 20,
			titleActionsOpen: false,
			editingTitle: false,
			pollMessageGenerationTimerId: null,
			pollTitleGenerationTimerId: null,
		}
	},

	watch: {
		async active() {
			this.allMessagesLoaded = false
			this.chatContent = ''
			this.msgCursor = 0
			this.messages = []
			this.editingTitle = false
			this.$refs.inputComponent.focus()

			if (this.active != null && !this.loading.newSession) {
				await this.fetchMessages()
				this.scrollToBottom()
			} else {
				// when no active session or creating a new session
				this.allMessagesLoaded = true
				this.loading.newSession = false
			}
		},
	},

	onDestroy() {
		if (this.pollMessageGenerationTimerId) {
			clearInterval(this.pollMessageGenerationTimerId)
		}
		if (this.pollTitleGenerationTimerId) {
			clearInterval(this.pollTitleGenerationTimerId)
		}
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

		onSessionSelect(session) {
			this.active = session
		},

		onEditSessionTitleClick() {
			this.editingTitle = true
			this.titleActionsOpen = false
		},

		async onEditSessionTitle(newTitle) {
			this.loading.updateTitle = true
			const session = this.sessions.find((session) => session.id === this.active.id)

			try {
				await axios.patch(getChatURL('/update_session'), {
					sessionId: this.active.id,
					title: newTitle,
				})
				this.editingTitle = false
				session.title = newTitle
			} catch (error) {
				console.error('updateTitle error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error updating title of conversation'))
			} finally {
				this.loading.updateTitle = false
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
				return session.title.length > 100 ? session.title.trim().slice(0, 100) + '...' : session.title.trim()
			}

			return session.timestamp ? (' ' + moment(session.timestamp * 1000).format('LLL')) : t('assistant', 'Untitled conversation')
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

			if (this.active == null) {
				await this.newSession()
			}

			this.messages.push({ role, content, timestamp })
			this.chatContent = ''
			this.scrollToBottom()
			await this.newMessage(role, content, timestamp)
		},

		onLoadOlderMessages() {
			if (this.loading.initialMessages || this.loading.olderMessages || this.allMessagesLoaded) {
				return
			}
			this.msgCursor += this.msgLimit
			this.fetchMessages(true)
		},

		async onGenerateSessionTitle() {
			try {
				this.loading.titleGeneration = true
				const response = await axios.get(getChatURL('/generate_title'), { params: { sessionId: this.active.id } })
				const titleResponse = await this.pollTitleGenerationTask(response.data.taskId)
				console.debug('checkTaskPolling result:', titleResponse)
				if (titleResponse?.data?.result == null) {
					throw new Error('No title generated, response:', response)
				}

				for (const session of this.sessions) {
					if (session.id === this.active.id) {
						session.title = titleResponse?.data?.result
						break
					}
				}
			} catch (error) {
				console.error('onGenerateSessionTitle error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error generating a title for the conversation'))
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
					this.active = null
				}
			} catch (error) {
				console.error('deleteSession error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error deleting conversation'))
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
				showError(error?.response?.data?.error ?? t('assistant', 'Error fetching conversations'))
			}
		},

		async deleteMessage(messageId) {
			try {
				this.loading.messageDelete = true
				await axios.delete(getChatURL('/delete_message'), {
					params: { messageId, sessionId: this.active.id },
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
				const firstHumanMessage = this.messages.length === 1 && this.messages[0].role === Roles.HUMAN

				const response = await axios.put(getChatURL('/new_message'), {
					sessionId: this.active.id,
					role,
					content,
					timestamp,
					firstHumanMessage,
				})
				console.debug('newMessage response:', response)
				this.loading.newHumanMessage = false

				// replace the last message with the response that contains the id
				this.messages[this.messages.length - 1] = response.data

				if (firstHumanMessage) {
					const session = this.sessions.find((session) => session.id === this.active.id)
					session.title = content
				}

				await this.runGenerationTask()
			} catch (error) {
				this.loading.newHumanMessage = false
				console.error('newMessage error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error creating a new message'))
			}
		},

		async newSession(title = null) {
			try {
				this.loading.newSession = true
				const response = await axios.put(getChatURL('/new_session'), {
					timestamp: +new Date() / 1000 | 0,
					title,
				})
				console.debug('newSession response:', response)

				const session = response.data?.session ?? null
				if (session == null) {
					throw new Error(t('assistant', 'Invalid response received for a new conversation request'))
				}

				this.sessions.unshift(session)
				this.active = session
				// newSession loading is reset in the active watcher
			} catch (error) {
				this.loading.newSession = false
				console.error('newSession error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error creating a new conversation'))
			}
		},

		async runGenerationTask() {
			try {
				this.loading.llmGeneration = true
				const response = await axios.get(getChatURL('/generate'), { params: { sessionId: this.active.id } })
				console.debug('scheduleGenerationTask response:', response)
				const message = await this.pollGenerationTask(response.data.taskId)
				console.debug('checkTaskPolling result:', message)
				this.messages.push(message)
				this.scrollToBottom()
			} catch (error) {
				console.error('scheduleGenerationTask error:', error)
				showError(t('assistant', 'Error generating a response'))
			} finally {
				this.loading.llmGeneration = false
			}
		},

		async runRegenerationTask(messageId) {
			try {
				this.loading.llmGeneration = true
				const response = await axios.get(getChatURL('/regenerate'), { params: { messageId, sessionId: this.active.id } })
				console.debug('scheduleRegenerationTask response:', response)
				const message = await this.pollGenerationTask(response.data.taskId)
				console.debug('checkTaskPolling result:', message)
				this.messages[this.messages.length - 1] = message
				this.scrollToBottom()
			} catch (error) {
				console.error('scheduleRegenerationTask error:', error)
				showError(t('assistant', 'Error regenerating a response'))
			} finally {
				this.loading.llmGeneration = false
			}
		},

		async pollGenerationTask(taskId) {
			return new Promise((resolve, reject) => {
				this.pollMessageGenerationTimerId = setInterval(() => {
					axios.get(
						getChatURL('/check_generation'),
						{ params: { taskId, sessionId: this.active.id } },
					).then(response => {
						clearInterval(this.pollMessageGenerationTimerId)
						resolve(response.data)
					}).catch(error => {
						// do not reject if response code is Http::STATUS_EXPECTATION_FAILED (417)
						if (error.response?.status !== 417) {
							console.error('checkTaskPolling error', error)
							clearInterval(this.pollMessageGenerationTimerId)
							reject(new Error('Message generation task check failed'))
						} else {
							console.debug('checkTaskPolling, task is still scheduled or running', error)
						}
					})
				}, 2000)
			})
		},

		async pollTitleGenerationTask(taskId) {
			return new Promise((resolve, reject) => {
				this.pollTitleGenerationTimerId = setInterval(() => {
					axios.get(
						getChatURL('/check_title_generation'),
						{ params: { taskId, sessionId: this.active.id } },
					).then(response => {
						clearInterval(this.pollTitleGenerationTimerId)
						resolve(response)
					}).catch(error => {
						// do not reject if response code is Http::STATUS_EXPECTATION_FAILED (417)
						if (error.response?.status !== 417) {
							console.error('checkTaskPolling error', error)
							clearInterval(this.pollTitleGenerationTimerId)
							reject(new Error('Title generation task check failed'))
						} else {
							console.debug('checkTaskPolling, task is still scheduled or running', error)
						}
					})
				}, 2000)
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	overflow: auto;
	display: flex;
	height: 100%;

	:deep .app-navigation-new {
		padding: 0;
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

		.app-navigation-toggle-wrapper {
			margin-right: -49px !important;
			top: var(--default-grid-baseline);
		}

		&--close {
			.app-navigation-toggle-wrapper {
				margin-right: -33px !important;
			}
		}

		&--close ~ .session-area {
			.session-area__chat-area, .session-area__input-area {
				padding-left: 0 !important;
			}
			.session-area__top-bar {
				padding-left: 36px !important;
			}
		}
	}

	:deep .app-navigation-list {
		padding: var(--default-grid-baseline) !important;
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
			justify-content: space-between;
			align-items: center;
			gap: 4px;
			position: sticky;
			top: 0;
			height: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 2);
			box-sizing: border-box;
			border-bottom: 1px solid var(--color-border);
			padding-left: 52px;
			padding-right: 0.5em;
			font-weight: bold;
			background-color: var(--color-main-background);

			&__title {
				width: 100%;
			}
		}

		&__chat-area {
			flex: 1;
			display: flex;
			flex-direction: column;
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
