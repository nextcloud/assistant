<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<NcAppNavigation>
			<NcAppNavigationList>
				<NcAppNavigationNew :text="t('assistant', 'New conversation')"
					variant="secondary"
					@click="newSession">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
				</NcAppNavigationNew>
				<div v-if="sessions == null" class="unloaded-sessions">
					<NcLoadingIcon :size="30" />
					{{ t('assistant', 'Loading conversations…') }}
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
						<NcActionButton @click="sessionIdToDelete = session.id">
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
						v-model:editing="editingTitle"
						:initial-text="getSessionTitle(active)"
						:placeholder="t('assistant', 'Conversation title')"
						:loading="loading.updateTitle"
						:max-length="100"
						@submit-text="onEditSessionTitle" />
				</div>
				<div v-if="active != null" class="session-area__top-bar__actions">
					<NcActions v-model:open="titleActionsOpen">
						<NcActionButton :disabled="loading.titleGeneration || editingTitle" @click="onEditSessionTitleClick">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('assistant', 'Edit title') }}
						</NcActionButton>
						<NcActionButton :disabled="loading.titleGeneration || editingTitle" @click="onGenerateSessionTitle">
							<template #icon>
								<AutoFixIcon v-if="!loading.titleGeneration" :size="20" />
								<NcLoadingIcon v-else :size="20" />
							</template>
							{{ t('assistant', 'Generate title') }}
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
							variant="secondary"
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
							variant="secondary"
							@click="runGenerationTask(active.id)">
							{{ t('assistant', 'Retry response generation') }}
						</NcButton>
					</div>
				</div>
			</div>
			<AgencyConfirmation v-if="active?.sessionAgencyPendingActions && active?.agencyAnswered === false"
				:actions="active?.sessionAgencyPendingActions"
				class="session-area__agency-confirmation"
				@confirm="onAgencyAnswer(true)"
				@reject="onAgencyAnswer(false)" />
			<InputArea ref="inputComponent"
				v-model:chat-content="chatContent"
				class="session-area__input-area"
				:loading="loading"
				@submit="handleSubmit" />
		</NcAppContent>
		<NcDialog :open="sessionIdToDelete !== null"
			:name="t('assistant', 'Conversation deletion')"
			:message="deletionConfirmationMessage"
			:container="null"
			@closing="sessionIdToDelete = null">
			<template #actions>
				<NcButton
					@click="sessionIdToDelete = null">
					{{ t('assistant', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="warning"
					@click="deleteSession(sessionIdToDelete)">
					<template #icon>
						<DeleteIcon />
					</template>
					{{ t('assistant', 'Delete') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import AutoFixIcon from 'vue-material-design-icons/AutoFix.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import AssistantIcon from '../icons/AssistantIcon.vue'
import DeleteIcon from '../icons/DeleteIcon.vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationList from '@nextcloud/vue/components/NcAppNavigationList'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import ConversationBox from './ConversationBox.vue'
import EditableTextField from './EditableTextField.vue'
import InputArea from './InputArea.vue'
import NoSession from './NoSession.vue'
import AgencyConfirmation from './AgencyConfirmation.vue'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import moment from 'moment'

// future: type (text, image, file, etc), attachments, etc support

const getChatURL = (endpoint) => generateOcsUrl('/apps/assistant/chat' + endpoint)
const Roles = {
	HUMAN: 'human',
	ASSISTANT: 'assistant',
}

export default {
	name: 'ChattyLLMInputForm',

	components: {
		AgencyConfirmation,
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
		NcDialog,

		ConversationBox,
		EditableTextField,
		InputArea,
		NoSession,
	},

	data: () => {
		return {
			// { id: number, title: string, user_id: string, timestamp: number }
			active: null,
			sessionIdToDelete: null,
			chatContent: '',
			sessions: null,
			// [{ id: number, session_id: number, role: string, content: string, timestamp: number, sources:string }]
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

	computed: {
		deletionConfirmationMessage() {
			if (this.sessions === null || this.sessionIdToDelete === null) {
				return ''
			}
			const session = this.sessions.find(s => s.id === this.sessionIdToDelete)
			const sessionTitle = this.getSessionTitle(session)?.trim()
			return t('assistant', 'Are you sure you want to delete "{sessionTitle}"?', { sessionTitle })
		},
	},

	watch: {
		async active() {
			this.allMessagesLoaded = false
			this.loading.llmGeneration = false
			this.loading.titleGeneration = false
			this.chatContent = ''
			this.msgCursor = 0
			this.messages = []
			this.editingTitle = false
			this.$refs.inputComponent.focus()

			if (this.active === null || this.loading.newSession) {
				this.allMessagesLoaded = true
				this.loading.newSession = false
				return
			}

			await this.fetchMessages()
			this.scrollToBottom()

			// start polling in case a message is currently being generated
			try {
				const sessionId = this.active.id
				const checkSessionResponse = await axios.get(getChatURL('/check_session'), { params: { sessionId } })
				const checkSessionResponseData = checkSessionResponse.data
				if (checkSessionResponseData?.sessionTitle && checkSessionResponseData?.sessionTitle !== this.active.title) {
					this.active.title = checkSessionResponseData?.sessionTitle
					console.debug('update session title with check result')
				}
				console.debug('check session response:', checkSessionResponseData)
				// update the pending actions when switching conversations
				this.active.sessionAgencyPendingActions = checkSessionResponseData?.sessionAgencyPendingActions
				this.active.agencyAnswered = false
				if (checkSessionResponseData.messageTaskId !== null) {
					try {
						this.loading.llmGeneration = true
						const message = await this.pollGenerationTask(checkSessionResponseData.messageTaskId, sessionId)
						console.debug('checkTaskPolling result:', message)
						this.messages.push(message)
						this.scrollToBottom()
					} catch (error) {
						console.error('checkGenerationTask error:', error)
						showError(t('assistant', 'Error generating a response'))
					}
				}
				if (checkSessionResponseData.titleTaskId !== null) {
					try {
						this.loading.titleGeneration = true
						const titleResponse = await this.pollTitleGenerationTask(checkSessionResponseData.titleTaskId, sessionId)
						const titleResponseData = titleResponse.data
						console.debug('checkTaskPolling result:', titleResponse)
						if (titleResponseData?.result == null) {
							throw new Error('No title generated, response:', titleResponse)
						}

						const session = this.sessions.find(s => s.id === sessionId)
						if (session) {
							session.title = titleResponseData?.result
						}
					} catch (error) {
						console.error('onCheckSessionTitle error:', error)
						showError(error?.response?.data?.error ?? t('assistant', 'Error getting the generated title for the conversation'))
					}
				}
			} catch (error) {
				console.error('check session error:', error)
				showError(t('assistant', 'Error checking if the session is thinking'))
			} finally {
				this.loading.llmGeneration = false
				this.loading.titleGeneration = false
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

			if (this.active === null) {
				await this.newSession()
			}

			// sending a message if there are pending actions means the user rejected the actions
			// so we can consider the agency confirmation answered
			if (this.active.sessionAgencyPendingActions) {
				this.active.agencyAnswered = true
			}

			this.messages.push({ role, content, timestamp })
			this.chatContent = ''
			this.scrollToBottom()
			await this.newMessage(role, content, timestamp, this.active.id)
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
				const sessionId = this.active.id
				const titleGenerationResponse = await axios.get(getChatURL('/generate_title'), { params: { sessionId } })
				const titlePollResponse = await this.pollTitleGenerationTask(titleGenerationResponse.data.taskId, sessionId)
				const titlePollResponseData = titlePollResponse.data
				console.debug('checkTaskPolling result:', titlePollResponseData)
				if (titlePollResponseData?.result == null) {
					throw new Error('No title generated, response:', titlePollResponse)
				}

				const session = this.sessions.find(s => s.id === sessionId)
				if (session) {
					session.title = titlePollResponseData?.result
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
				this.sessionIdToDelete = null
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

				const messagesResponse = await axios.get(getChatURL('/messages'), {
					params: {
						sessionId: this.active.id,
						cursor: this.msgCursor,
						limit: this.msgLimit,
					},
					signal: this.messagesAxiosController.signal,
				})
				const messagesResponseData = messagesResponse.data
				console.debug('fetchMessages response:', messagesResponseData)
				if (this.messages == null) {
					this.messages = []
				}
				this.messages.unshift(...messagesResponseData)

				if (messagesResponseData.length < this.msgLimit) {
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

		async newMessage(role, content, timestamp, sessionId, replaceLastMessage = true, agencyConfirm = null) {
			try {
				this.loading.newHumanMessage = true
				const firstHumanMessage = this.messages.length === 1 && this.messages[0].role === Roles.HUMAN

				const newMessageResponse = await axios.put(getChatURL('/new_message'), {
					sessionId,
					role,
					content,
					timestamp,
					firstHumanMessage,
				})
				const newMessageResponseData = newMessageResponse.data
				console.debug('newMessage response:', newMessageResponseData)
				this.loading.newHumanMessage = false

				if (replaceLastMessage) {
					// replace the last message with the response that contains the id
					this.messages[this.messages.length - 1] = newMessageResponseData
				}

				if (firstHumanMessage) {
					const session = this.sessions.find((session) => session.id === sessionId)
					session.title = content
				}

				await this.runGenerationTask(sessionId, agencyConfirm)
			} catch (error) {
				this.loading.newHumanMessage = false
				console.error('newMessage error:', error)
				showError(error?.response?.data?.error ?? t('assistant', 'Error creating a new message'))
			}
		},

		async newSession(title = null) {
			try {
				this.loading.newSession = true
				const newSessionResponse = await axios.put(getChatURL('/new_session'), {
					timestamp: +new Date() / 1000 | 0,
					title,
				})
				const newSessionResponseData = newSessionResponse.data
				console.debug('newSession response:', newSessionResponseData)

				const session = newSessionResponseData?.session ?? null
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

		async runGenerationTask(sessionId, agencyConfirm = null) {
			try {
				this.loading.llmGeneration = true
				const params = {
					sessionId,
				}
				if (agencyConfirm !== null) {
					params.agencyConfirm = agencyConfirm ? 1 : 0
				}
				this.saveLastSelectedTaskType('chatty-llm')
				const generationResponse = await axios.get(getChatURL('/generate'), { params })
				const generationResponseData = generationResponse.data
				console.debug('scheduleGenerationTask response:', generationResponseData)
				const message = await this.pollGenerationTask(generationResponseData.taskId, sessionId)
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
				const sessionId = this.active.id
				this.loading.llmGeneration = true
				const regenerationResponse = await axios.get(getChatURL('/regenerate'), { params: { messageId, sessionId } })
				const regenerationResponseData = regenerationResponse.data
				console.debug('scheduleRegenerationTask response:', regenerationResponse)
				const message = await this.pollGenerationTask(regenerationResponseData.taskId, sessionId)
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

		async pollGenerationTask(taskId, sessionId) {
			return new Promise((resolve, reject) => {
				this.pollMessageGenerationTimerId = setInterval(() => {
					if (sessionId !== this.active.id) {
						console.debug('Stop polling messages for session ' + sessionId + ' because it is not selected anymore')
						clearInterval(this.pollMessageGenerationTimerId)
						return
					}
					axios.get(
						getChatURL('/check_generation'),
						{ params: { taskId, sessionId } },
					).then(response => {
						const responseData = response.data
						clearInterval(this.pollMessageGenerationTimerId)
						if (sessionId === this.active.id) {
							this.active.sessionAgencyPendingActions = responseData.sessionAgencyPendingActions
							this.active.agencyAnswered = false
							resolve(responseData)
						} else {
							console.debug('Ignoring received message for session ' + sessionId + ' that is not selected anymore')
							// should we reject here?
						}
					}).catch(error => {
						// do not reject if response code is Http::STATUS_EXPECTATION_FAILED (417)
						if (error.response?.status !== 417) {
							console.error('checkTaskPolling error', error)
							clearInterval(this.pollMessageGenerationTimerId)
							reject(new Error('Message generation task check failed'))
						} else {
							console.debug('checkTaskPolling, task is still scheduled or running')
						}
					})
				}, 2000)
			})
		},

		async pollTitleGenerationTask(taskId, sessionId) {
			return new Promise((resolve, reject) => {
				this.pollTitleGenerationTimerId = setInterval(() => {
					if (sessionId !== this.active.id) {
						console.debug('Stop polling title for session ' + sessionId + ' because it is not selected anymore')
						clearInterval(this.pollTitleGenerationTimerId)
						return
					}
					axios.get(
						getChatURL('/check_title_generation'),
						{ params: { taskId, sessionId } },
					).then(response => {
						if (sessionId === this.active.id) {
							resolve(response)
						} else {
							console.debug('Ignoring received title for session ' + sessionId + ' that is not selected anymore')
							// should we reject here?
						}
						clearInterval(this.pollTitleGenerationTimerId)
					}).catch(error => {
						// do not reject if response code is Http::STATUS_EXPECTATION_FAILED (417)
						if (error.response?.status !== 417) {
							console.error('checkTaskPolling error', error)
							clearInterval(this.pollTitleGenerationTimerId)
							reject(new Error('Title generation task check failed'))
						} else {
							console.debug('checkTaskPolling, task is still scheduled or running')
						}
					})
				}, 2000)
			})
		},
		async onAgencyAnswer(confirm) {
			this.active.agencyAnswered = true
			// send accept/reject message
			const role = Roles.HUMAN
			const content = ''
			const timestamp = +new Date() / 1000 | 0

			if (this.active === null) {
				await this.newSession()
			}

			// this.messages.push({ role, content, timestamp })
			this.chatContent = ''
			this.scrollToBottom()
			await this.newMessage(role, content, timestamp, this.active.id, false, confirm)
		},

		async saveLastSelectedTaskType(taskType) {
			const req = {
				values: {
					last_task_type: taskType,
				},
			}
			const url = generateUrl('/apps/assistant/config')
			return axios.put(url, req)
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	overflow: auto;
	display: flex;
	height: 100%;

	:deep(.app-navigation-new) {
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

	:deep(.app-navigation) {
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
	}

	:deep(.app-navigation--close) {
		.app-navigation-toggle-wrapper {
			margin-right: -33px !important;
		}
	}

	:deep(.app-navigation--close ~ .session-area) {
		.session-area__chat-area, .session-area__input-area {
			padding-left: 0 !important;
		}
		.session-area__top-bar {
			padding-left: 36px !important;
		}
	}

	:deep(.app-navigation-list) {
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

		&__agency-confirmation {
			margin-left: 1em;
		}

		&__input-area {
			position: sticky;
			bottom: 0;
		}
	}
}
</style>
