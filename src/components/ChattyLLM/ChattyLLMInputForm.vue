<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->
<template>
	<div class="container">
		<NcAppNavigation>
			<NcAppNavigationList>
				<NcAppNavigationNew v-if="!isAssignment"
					:text="t('assistant', 'New conversation')"
					variant="secondary"
					@click="newSession">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
				</NcAppNavigationNew>
				<div v-if="sessions == null" class="unloaded-sessions">
					<NcLoadingIcon :size="30" />
					{{ isAssignment ? t('assistant', 'Loading scheduled tasks…') : t('assistant', 'Loading conversations…') }}
				</div>
				<div v-else-if="sessions != null && sessions.length === 0" class="unloaded-sessions">
					{{ isAssignment ? t('assistant', 'No scheduled tasks yet') : t('assistant', 'No conversations yet') }}
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
								<TrashCanOutlineIcon v-if="!loading.sessionDelete" :size="20" />
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
				<div class="session-area__top-bar__main">
					<div class="session-area__top-bar__title">
						<EditableTextField v-if="active != null"
							v-model:editing="editingTitle"
							:initial-text="getSessionTitle(active)"
							:placeholder="t('assistant', 'Conversation title')"
							:loading="loading.updateTitle"
							:max-length="100"
							@submit-text="onEditSessionTitle" />
					</div>
					<div v-if="isAssignment && assignmentDetails?.id && active?.assignment_id === assignmentDetails?.id" class="session-area__top-bar__details">
						<div class="session-area__top-bar__detail">
							{{ t('assistant', 'Prompt: {prompt}', {prompt : assignmentDetails.prompt}) }}
						</div>
						<div class="session-area__top-bar__detail">
							{{ t('assistant', 'Recurrence: {recurrence}', {recurrence : rrule}) }}
						</div>
					</div>
				</div>
				<div v-if="active != null && !isAssignment" class="session-area__top-bar__remember">
					<NcCheckboxRadioSwitch v-model="active.is_remembered" type="switch" @update:modelValue="updateSession">
						{{ t('assistant', 'Remember this') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div v-if="active != null" class="session-area__top-bar__actions">
					<NcActions v-model:open="titleActionsOpen">
						<NcActionButton v-if="!isAssignment"
							v-model="active.is_remembered"
							type="checkbox"
							@update:modelValue="updateSession">
							<template #icon>
								<MemoryIcon :size="20" />
							</template>
							{{ t('assistant', 'Remember this') }}
						</NcActionButton>
						<NcActionButton :disabled="loading.titleGeneration || editingTitle" @click="onEditSessionTitleClick">
							<template #icon>
								<PencilOutlineIcon :size="20" />
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
				<NoSession v-else-if="isAssignment && active != null && (!loading.initialMessages && (messages?.length ?? 0) === 0)"
					:name="t('assistant', 'No results yet')"
					:description="t('assistant', 'This task has not run yet. Results will appear here after the next scheduled run.')">
					<template #icon>
						<TimerOutlineIcon />
					</template>
				</NoSession>
				<NoSession v-else-if="isAssignment && active == null"
					:name="t('assistant', 'No scheduled tasks yet')"
					:description="t('assistant', 'Scheduled tasks run automatically on a recurring schedule. Ask chat to create one for you.')">
					<template #icon>
						<TimerOutlineIcon />
					</template>
					<template #action>
						<NcButton variant="primary" @click="openChatToCreateAssignment">
							{{ t('assistant', 'Create in chat') }}
						</NcButton>
					</template>
				</NoSession>
				<NoSession v-else-if="!isAssignment && (active == null || (!loading.initialMessages && (messages?.length ?? 0) === 0))"
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
						:streaming-message="streamingMessage"
						:loading="loading"
						:slow-pickup="slowPickup"
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
			<p class="session-area__disclaimer">
				{{ t('assistant', 'Output shown here is generated by AI. Make sure to always double-check.') }}
			</p>
			<div v-if="agencyAvailable && (messages == null || messages.length === 0) && !isAssignment" class="session-area__agency-suggestions">
				<NcButton v-for="suggestion in agencySuggestions"
					:key="suggestion.message"
					class="session-area__agency-suggestion"
					:aria-label="suggestion.aria"
					variant="tertiary"
					:text="suggestion.message"
					@click="chatContent = suggestion.message" />
			</div>
			<p v-if="chatContent?.length > 64_000"
				class="session-area__disclaimer">
				{{ t('assistant', 'Messages should not be longer than {maxLength} characters (currently {length}).', { maxLength: 64_000, length: chatContent.length }) }}
			</p>
			<InputArea v-if="!isAssignment"
				ref="inputComponent"
				v-model:chat-content="chatContent"
				class="session-area__input-area"
				:loading="loading"
				@submit="handleSubmit"
				@submit-audio="handleSubmitAudio" />
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
						<TrashCanOutlineIcon />
					</template>
					{{ t('assistant', 'Delete') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import AutoFixIcon from 'vue-material-design-icons/AutoFix.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import MemoryIcon from 'vue-material-design-icons/Memory.vue'
import TimerOutlineIcon from 'vue-material-design-icons/TimerOutline.vue'

import AssistantIcon from '../icons/AssistantIcon.vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
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

import axios, { isCancel } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { listen } from '@nextcloud/notify_push'
import moment from 'moment'
import { SHAPE_TYPE_NAMES, TASK_STATUS_INT } from '../../constants.js'
import ICAL from 'ical.js'
import formatRecurrenceRule from './recurrenceRule.js'
import { getLanguage } from '@nextcloud/l10n'

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
		TrashCanOutlineIcon,
		PencilOutlineIcon,
		PlusIcon,
		MemoryIcon,
		TimerOutlineIcon,

		AssistantIcon,

		NcActionButton,
		NcCheckboxRadioSwitch,
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

	props: {
		isAssignment: {
			type: Boolean,
			required: true,
		},
	},

	emits: [
		'open-chat',
	],

	data: () => {
		return {
			// { id: number, title: string, user_id: string, timestamp: number }
			active: null,
			sessionIdToDelete: null,
			chatContent: '',
			sessions: null,
			assignmentDetails: null,
			pollCheckSessionTimeout: null,
			// [{ id: number, session_id: number, role: string, content: string, timestamp: number, sources:string }]
			messages: [], // null when failed to fetch
			streamingMessage: null,
			isListeningTo: {},
			messagesAxiosController: null, // for request cancellation
			allMessagesLoaded: false,
			loading: {
				initialMessages: false,
				olderMessages: false,
				llmGeneration: false,
				llmRunning: false,
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
			autoplayAudioChat: loadState('assistant', 'autoplay_audio_chat', true),
			slowPickup: false,
			agencyAvailable: loadState('assistant', 'agency_available', false),
			agencySuggestions: [
				{
					aria: t('assistant', 'Ask assistant, what\'s the weather today'),
					message: t('assistant', 'What\'s the weather today?'),
				},
				{
					aria: t('assistant', 'Ask assistant, to create a share link for a file'),
					message: t('assistant', 'Can you create a share link for me?'),
				},
				{
					aria: t('assistant', 'Ask assistant, to create a scheduled task to send me the weather every morning'),
					message: t('assistant', 'Create a scheduled task to send me the weather every morning '),
				},
				{
					aria: t('assistant', 'Ask assistant, which actions it can do for you'),
					message: t('assistant', 'Which actions can you do for me?'),
				},
				{
					aria: t('assistant', 'Ask assistant for route from Munich to Berlin using public transport'),
					message: t('assistant', 'Can you give me a route from Munich to Berlin using public transport?'),
				},
				{
					aria: t('assistant', 'Ask assistant to transcribe a media file for you'),
					message: t('assistant', 'Transcribe a media file for me'),
				},
				{
					aria: t('assistant', 'Ask assistant to schedule an event for a Design meeting whenever you\'re free tomorrow'),
					message: t('assistant', 'Schedule an event for a Design meeting whenever I\'m free tomorrow'),
				},
				{
					aria: t('assistant', 'Ask assistant to create a deck card in my Project board in the todo stack for creating the presentation'),
					message: t('assistant', 'Create a deck card in my Project board in the todo stack for creating the presentation'),
				},
				{
					aria: t('assistant', 'Ask assistant to generate a slide deck about the features of Nextcloud'),
					message: t('assistant', 'Generate a slide deck about the features of Nextcloud'),
				},
				{
					aria: t('assistant', 'Ask assistant to generate an image of a puppy with a Nextcloud hat'),
					message: t('assistant', 'Generate an image of a puppy with a Nextcloud hat'),
				},
			].sort(() => Math.round(Math.random() - 1)).slice(0, 2).concat(
				{
					aria: t('assistant', 'Ask assistant, which actions it can do for you'),
					message: t('assistant', 'Which actions can you do for me?'),
				},
			),
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
		rrule() {
			const raw = this.assignmentDetails?.recurrence ?? ''
			if (raw === '') {
				return ''
			}
			try {
				const iCalRecur = ICAL.Recur.fromString(raw)
				const obj = {
					frequency: iCalRecur.freq,
					interval: iCalRecur.interval || 1,
					count: iCalRecur.count ?? null,
					until: iCalRecur.until ? new Date(iCalRecur.until) : null,
					byDay: iCalRecur.parts.BYDAY ?? [],
					byMonth: iCalRecur.parts.BYMONTH ?? [],
					byMonthDay: iCalRecur.parts.BYMONTHDAY ?? [],
					bySetPosition: iCalRecur.parts.BYSETPOS?.[0] ?? null,
				}
				console.debug('rrule:', obj)
				const locale = getLanguage()
				return formatRecurrenceRule(obj, locale)
			} catch (error) {
				return raw
			}
		},
	},

	watch: {
		async active() {
			this.allMessagesLoaded = false
			this.loading.llmGeneration = false
			this.loading.llmRunning = false
			this.loading.titleGeneration = false
			this.streamingMessage = null
			this.chatContent = ''
			this.msgCursor = 0
			this.messages = []
			this.editingTitle = false
			if (this.$refs.inputComponent) {
				this.$refs.inputComponent.focus()

			}

			if (this.active === null || this.loading.newSession) {
				this.allMessagesLoaded = true
				this.loading.newSession = false
				return
			}

			await this.fetchMessages()
			this.scrollToBottom()

			// start polling in case a message is currently being generated
			this.checkSession(this.active.id, this.isAssignment)
		},
		isAssignment() {
			this.fetchSessions()
			this.active = null
			clearTimeout(this.pollCheckSessionTimeout)
		},
	},

	beforeUnmount() {
		if (this.pollMessageGenerationTimerId) {
			clearInterval(this.pollMessageGenerationTimerId)
		}
		if (this.pollTitleGenerationTimerId) {
			clearInterval(this.pollTitleGenerationTimerId)
		}
		if (this.pollCheckSessionTimeout) {
			clearTimeout(this.pollCheckSessionTimeout)
		}
	},

	mounted() {
		this.fetchSessions()
	},

	methods: {
		async checkSession(sessionId, isAssignment) {
			try {
				if (this.active?.id == null || this.active?.id !== sessionId) {
					return
				}
				if (this.pollCheckSessionTimeout) {
					clearTimeout(this.pollCheckSessionTimeout)
				}
				const checkSessionResponse = await axios.get(getChatURL('/check_session'), { params: { sessionId } })
				const checkSessionResponseData = checkSessionResponse.data
				if (checkSessionResponseData?.sessionTitle && checkSessionResponseData?.sessionTitle !== this.active.title) {
					this.active.title = checkSessionResponseData?.sessionTitle
					console.debug('update session title with check result')
				}
				console.debug('check session response:', checkSessionResponseData)
				this.active.is_remembered = checkSessionResponseData?.is_remembered
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
					this.streamingMessage = null
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
				this.loading.llmRunning = false
				this.loading.titleGeneration = false
				if (isAssignment) {
					this.pollCheckSessionTimeout = setTimeout(() => { this.checkSession(sessionId, isAssignment) }, 5000)
				}
			}
		},
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
				document.querySelector('#message-streaming')?.scrollIntoView()
				document.querySelector('#message-placeholder')?.scrollIntoView()
				this.$refs.inputComponent.focus()
				if (!this.isAssignment) {
					this.$refs.inputComponent.focus()
				}
			})
		},

		onSessionSelect(session) {
			this.assignmentDetails = null
			this.active = session
			if (this.isAssignment) {
				this.fetchAssignmentDetails(session.assignment_id)
			}
		},

		fetchAssignmentDetails(sessionId) {
			const url = generateOcsUrl(`/apps/assistant/assignments/${sessionId}`)
			axios.get(url).then(response => {
				const assignmentDetails = response.data
				console.debug('Assignment details:', assignmentDetails)
				this.assignmentDetails = assignmentDetails.ocs.data.assignment
			}).catch(error => {
				console.error('Error fetching assignment details:', error)
			})
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

			const role = Roles.HUMAN
			const content = this.chatContent.trim()
			const timestamp = +new Date() / 1000 | 0
			console.debug('[Assistant] submit text', content)

			if (this.active === null) {
				await this.newSession()
			}

			// sending a message if there are pending actions means the user rejected the actions
			// so we can consider the agency confirmation answered
			if (this.active.sessionAgencyPendingActions) {
				this.active.agencyAnswered = true
			}

			this.messages.push({ role, content, timestamp, session_id: this.active.id })
			this.chatContent = ''
			this.scrollToBottom()
			await this.newMessage(role, content, timestamp, this.active.id)
		},

		async handleSubmitAudio(fileId) {
			console.debug('[Assistant] submit audio', fileId)
			const role = Roles.HUMAN
			const content = ''
			const timestamp = +new Date() / 1000 | 0
			const attachments = [{ type: SHAPE_TYPE_NAMES.Audio, file_id: fileId }]

			if (this.active === null) {
				await this.newSession()
			}

			// sending a message if there are pending actions means the user rejected the actions
			// so we can consider the agency confirmation answered
			if (this.active.sessionAgencyPendingActions) {
				this.active.agencyAnswered = true
			}

			this.messages.push({ role, content, timestamp, session_id: this.active.id, attachments })
			this.chatContent = ''
			this.scrollToBottom()
			await this.newMessage(role, content, timestamp, this.active.id, attachments)
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
				this.sessions = null
				const response = await axios.get(getChatURL('/sessions'), { params: { isAssignment: this.isAssignment } })
				console.debug('fetchSessions response:', response)
				this.sessions = response.data
			} catch (error) {
				this.sessions = []
				console.error('fetchSessions error:', error)
				const fallbackError = this.isAssignment ? t('assistant', 'Error fetching assignments') : t('assistant', 'Error fetching conversations')
				showError(error?.response?.data?.error ?? fallbackError)
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
						filterByRole: this.isAssignment ? 'assistant' : undefined,
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
				if (isCancel(error)) {
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

		async newMessage(role, content, timestamp, sessionId, attachments = null, replaceLastMessage = true, agencyConfirm = null) {
			try {
				this.loading.newHumanMessage = true
				const firstHumanMessage = this.messages.length === 1 && this.messages[0].role === Roles.HUMAN

				const newMessageResponse = await axios.put(getChatURL('/new_message'), {
					sessionId,
					role,
					content,
					attachments,
					timestamp,
					firstHumanMessage,
				})
				const newMessageResponseData = newMessageResponse.data
				console.debug('newMessage response:', newMessageResponseData)
				this.loading.newHumanMessage = false

				// we need the ID of the messages, even right after they have been added
				this.messages[this.messages.length - 1].id = newMessageResponseData.id

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
				this.scrollToBottom()
				this.slowPickup = false
				this.loading.llmGeneration = true
				this.loading.llmRunning = false
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
				this.loading.llmRunning = false
				this.streamingMessage = null
			}
		},

		async runRegenerationTask(messageId) {
			try {
				const sessionId = this.active.id
				this.loading.llmGeneration = true
				this.loading.llmRunning = false
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
				this.loading.llmRunning = false
				this.streamingMessage = null
			}
		},

		listenToTaskNotifications(pushTaskId, pushSessionId) {
			// attempt to listen to push notifications to get the intermediate output
			if (this.isListeningTo[pushTaskId]) {
				return true
			}
			const pushChannel = 'task_' + pushTaskId
			const hasPush = listen(pushChannel, (type, body) => {
				console.debug('[assistant] received push notification', type, body)
				const activeSessionId = this.active?.id
				if (pushSessionId === activeSessionId) {
					this.updateStreamingMessage(body?.output ?? '', pushSessionId)
				} else {
					console.debug(
						'[assistant] ignoring push notification for task',
						pushTaskId,
						'in session',
						pushSessionId,
						'the selected session is',
						this.active?.id,
					)
				}

			})
			if (hasPush) {
				this.isListeningTo[pushTaskId] = true
			}
			return hasPush
		},

		async pollGenerationTask(taskId, sessionId) {
			const hasPush = this.listenToTaskNotifications(taskId, sessionId)
			console.debug('[assistant] HAS PUSH', hasPush)

			return new Promise((resolve, reject) => {
				this.pollMessageGenerationTimerId = setInterval(() => {
					if (this.active === null || sessionId !== this.active.id) {
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
							// update content of previous message if we receive an audio message from the assistant
							// or if the last human message had an audio attachment
							if (this.doesLastHumanMessageHaveAudio()
								|| (responseData.role === Roles.ASSISTANT && responseData.attachments.find(a => a.type === SHAPE_TYPE_NAMES.Audio))
							) {
								this.updateLastHumanMessageContent()
							}
							if (this.autoplayAudioChat) {
								// auto play fresh messages
								responseData.autoPlay = true
							}
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
							this.slowPickup = error.response.data.slow_pickup
							if (error.response.data.task_status === TASK_STATUS_INT.running) {
								this.loading.llmRunning = true
							}
							if (!hasPush && error.response.data.task_output?.output) {
								this.updateStreamingMessage(error.response.data.task_output.output, sessionId)
							}
						}
					})
				}, 2000)
			})
		},

		updateStreamingMessage(content, sessionId) {
			if (this.streamingMessage) {
				this.streamingMessage.content = content
			} else {
				this.streamingMessage = {
					role: Roles.ASSISTANT,
					content,
					attachments: [],
					sources: '',
					session_id: sessionId,
					id: 0,
					timestamp: moment().unix(),
				}
			}
		},

		getLastHumanMessage() {
			return this.messages
				.filter(m => m.role === Roles.HUMAN)
				.pop()
		},

		doesLastHumanMessageHaveAudio() {
			const lastHumanMessage = this.getLastHumanMessage()
			if (lastHumanMessage) {
				return lastHumanMessage.attachments.find(a => a.type === SHAPE_TYPE_NAMES.Audio)
			}
			return false
		},

		async updateSession() {
			await axios.put(getChatURL(`/sessions/${this.active.id}`), {
				title: this.active.title,
				is_remembered: this.active.is_remembered,
			})

		},

		async updateLastHumanMessageContent() {
			const lastHumanMessage = this.getLastHumanMessage()
			if (lastHumanMessage) {
				const updatedMessage = await axios.get(
					getChatURL(`/sessions/${lastHumanMessage.session_id}/messages/${lastHumanMessage.id}`),
				)
				lastHumanMessage.content = updatedMessage.data.content
				// update session title (just in the frontend data, the db session is updated in the backend listener)
				const isFirstHumanMessage = this.messages.filter(m => m.role === Roles.HUMAN).length === 1
				if (isFirstHumanMessage) {
					const session = this.sessions.find((session) => session.id === lastHumanMessage.session_id)
					session.title = updatedMessage.data.content
				}
			}
		},

		async pollTitleGenerationTask(taskId, sessionId) {
			return new Promise((resolve, reject) => {
				this.pollTitleGenerationTimerId = setInterval(() => {
					if (this.active === null || sessionId !== this.active.id) {
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
			await this.newMessage(role, content, timestamp, this.active.id, null, false, confirm)
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

		openChatToCreateAssignment() {
			this.$emit('open-chat')
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	align-items: stretch;
	flex: 1 1 auto;
	width: 100%;
	height: 100%;
	min-height: 0;
	overflow: hidden;

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
		flex: 0 0 auto;
		height: 100%;
		min-height: 0;
		overflow: visible;

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
		min-height: 0;
		overflow-y: auto;
		overflow-x: hidden;

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

	:deep(.app-content-vue) {
		flex: 1 1 auto;
		height: 100%;
		min-height: 0;
		overflow-y: auto;
	}

	.session-area {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		min-width: 0;
		min-height: 0;

		&__top-bar {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			gap: 4px;
			position: sticky;
			top: 0;
			min-height: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 2);
			box-sizing: border-box;
			border-bottom: 1px solid var(--color-border);
			padding-left: 52px;
			padding-right: 0.5em;
			padding-top: var(--default-grid-baseline);
			padding-bottom: var(--default-grid-baseline);
			font-weight: bold;
			background-color: var(--color-main-background);

			&__main {
				margin-top: calc(var(--default-grid-baseline) * -1);
				display: flex;
				flex: 1;
				flex-direction: column;
				gap: 4px;
				min-width: 0;
			}

			&__title {
				width: 100%;
				overflow-x: auto;
				white-space: nowrap;
			}

			&__details {
				display: flex;
				flex-wrap: wrap;
				gap: 6px;
				font-size: 0.9em;
				font-weight: normal;
			}

			&__detail {
				border: 1px solid var(--color-border);
				border-radius: 999px;
				padding: 2px 10px;
				background-color: var(--color-background-hover);
				color: var(--color-text-maxcontrast);
				white-space: nowrap;
				max-width: 100%;
				overflow: hidden;
				text-overflow: ellipsis;
			}

			&__remember {
				white-space: nowrap;
				@media (max-width: 600px) {
					display: none;
				}
			}
		}

		&__chat-area {
			flex: 1;
			display: flex;
			flex-direction: column;
			min-height: 0;
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

		&__disclaimer {
			align-self: center;
			color: var(--color-text-lighter);
			margin: 0.5em 0;
		}

		&__agency-confirmation {
			margin-left: 1em;
		}

		&__input-area {
			position: sticky;
			bottom: 0;
			flex-shrink: 0;
		}

		&__agency-suggestions {
			display: flex;
			flex-direction: row;
			align-items: start;
			gap: 10px;
			flex-wrap: wrap;
			justify-content: start;
			padding: 0 1em;
		}
		&__agency-suggestion {
			flex-shrink: 0;
		}
	}
}
</style>
