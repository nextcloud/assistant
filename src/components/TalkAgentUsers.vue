<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="talk-agents">
		<NcNoteCard type="info">
			{{ t('assistant', 'Agents are dedicated Nextcloud users with their own personality. Users can invite them in Talk conversations and mention them; each mention is handled in a private 1-1 Talk conversation between the agent and the user. When an agent is deleted, the related user can be deleted too.') }}
		</NcNoteCard>

		<ul class="talk-agents__list">
			<li v-for="agent in agents"
				:key="agent.id"
				class="talk-agents__row">
				<NcAvatar :user="agent.user_id"
					:hide-status="true"
					:disable-menu="true" />
				<div class="talk-agents__info">
					<span class="talk-agents__name">{{ agent.display_name }}</span>
					<span class="talk-agents__uid">{{ agent.user_id }}</span>
					<span v-if="agent.personality" class="talk-agents__personality">{{ agent.personality }}</span>
				</div>
				<div class="talk-agents__actions">
					<NcButton variant="tertiary"
						:aria-label="t('assistant', 'Edit agent')"
						@click="openEditModal(agent)">
						<template #icon>
							<PencilIcon :size="16" />
						</template>
					</NcButton>
					<NcButton variant="tertiary"
						:aria-label="t('assistant', 'Delete agent')"
						@click="onDeleteClicked(agent)">
						<template #icon>
							<DeleteOutlineIcon :size="16" />
						</template>
					</NcButton>
				</div>
			</li>
			<li v-if="agents.length === 0" class="talk-agents__empty">
				{{ t('assistant', 'No agent configured yet') }}
			</li>
		</ul>

		<NcButton variant="secondary"
			@click="showCreateModal = true">
			<template #icon>
				<AccountPlusOutlineIcon :size="16" />
			</template>
			{{ t('assistant', 'Create an agent') }}
		</NcButton>

		<!-- create modal -->
		<NcDialog v-if="showCreateModal"
			:name="t('assistant', 'Create an agent')"
			size="normal"
			@closing="showCreateModal = false">
			<div class="talk-agents__form">
				<NcTextField v-model="newAgent.name"
					:label="t('assistant', 'Agent name')"
					:helper-text="t('assistant', 'The display name of the agent\'s Nextcloud user, e.g. spent in Talk')" />
				<NcTextField v-model="newAgent.userId"
					:label="t('assistant', 'User id')"
					:helper-text="t('assistant', 'The id of the agent\'s Nextcloud user, created in the default database backend. Lowercase letters, digits, dot, dash, underscore and @ only.')" />
				<NcRichContenteditable v-model="newAgent.personality"
					class="text-field"
					:auto-complete="() => {}"
					:link-auto-complete="false"
					:aria-label="t('assistant', 'Agent personality (system prompt)')"
					:placeholder="t('assistant', 'Describe the agent\'s personality, tone, what it can do, in which language it should reply…')" />
				<NcTextField v-model="newAgent.scope"
					:label="t('assistant', 'Scope (optional)')" />
				<NcSelectUsers
					v-model="newAgent.access"
					class="talk-agents__select"
					:multiple="true"
					:input-label="t('assistant', 'Allowed users and groups (optional)')"
					:options="accessOptions"
					:loading="searchingAccess"
					@search="onSearchAccess" />
				<p class="talk-agents__access-hint">
					{{ t('assistant', 'Leave the users and groups empty so that every user can use this agent.') }}
				</p>
			</div>
			<template #actions>
				<NcButton variant="secondary"
					:disabled="creating"
					@click="showCreateModal = false">
					{{ t('assistant', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="creating || !newAgent.name || !newAgent.userId"
					@click="createAgent">
					{{ creating ? t('assistant', 'Creating…') : t('assistant', 'Create') }}
				</NcButton>
			</template>
		</NcDialog>

		<!-- edit modal -->
		<NcDialog v-if="showEditModal"
			:name="t('assistant', 'Edit agent')"
			size="normal"
			@closing="showEditModal = false">
			<div class="talk-agents__form">
				<NcTextField v-model="editingAgentData.display_name"
					:label="t('assistant', 'Agent')"
					:readonly="true" />
				<NcRichContenteditable v-model="editingAgentData.personality"
					class="text-field"
					:auto-complete="() => {}"
					:link-auto-complete="false"
					:aria-label="t('assistant', 'Agent personality (system prompt)')"
					:placeholder="t('assistant', 'Describe the agent\'s personality, tone, what it can do, in which language it should reply…')" />
				<NcTextField v-model="editingAgentData.scope"
					:label="t('assistant', 'Scope (optional)')" />
				<NcSelectUsers
					v-model="editingAgentData.access"
					class="talk-agents__select"
					:multiple="true"
					:input-label="t('assistant', 'Allowed users and groups (optional)')"
					:options="accessOptions"
					:loading="searchingAccess"
					@search="onSearchAccess" />
				<p class="talk-agents__access-hint">
					{{ t('assistant', 'Leave the users and groups empty so that every user can use this agent.') }}
				</p>
			</div>
			<template #actions>
				<NcButton variant="secondary"
					@click="showEditModal = false">
					{{ t('assistant', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="saving"
					@click="saveAgent">
					{{ saving ? t('assistant', 'Saving…') : t('assistant', 'Save') }}
				</NcButton>
			</template>
		</NcDialog>

		<!-- delete confirmation -->
		<NcDialog v-if="deletingAgent"
			:name="t('assistant', 'Delete agent')"
			size="normal"
			@closing="deletingAgent = null">
			<p>
				{{ t('assistant', 'Delete the agent "{agentName}"?', { agentName: deletingAgent.display_name }) }}
			</p>
			<p>
				<NcCheckboxRadioSwitch v-model="alsoDeleteUser" type="switch">
					{{ t('assistant', 'Also delete the related Nextcloud user "{userId}"', { userId: deletingAgent.user_id }) }}
				</NcCheckboxRadioSwitch>
			</p>
			<template #actions>
				<NcButton variant="secondary"
					@click="deletingAgent = null">
					{{ t('assistant', 'Cancel') }}
				</NcButton>
				<NcButton variant="error"
					:disabled="saving"
					@click="deleteAgent">
					{{ t('assistant', 'Delete') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import AccountPlusOutlineIcon from 'vue-material-design-icons/AccountPlusOutline.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

const agentsUrl = () => generateOcsUrl('/apps/assistant/api/v1/agents')
const agentUrl = (id) => generateOcsUrl(`/apps/assistant/api/v1/agents/${id}`)

const GROUP_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
	+ '<path fill="currentColor" d="M12,5A3.5,3.5 0 0,0 8.5,8.5A3.5,3.5 0 0,0 12,12A3.5,3.5 0 0,0 15.5,8.5A3.5,3.5 0 0,0 12,5M12,7A1.5,1.5 0 0,1 13.5,8.5A1.5,1.5 0 0,1 12,10A1.5,1.5 0 0,1 10.5,8.5A1.5,1.5 0 0,1 12,7M5.5,8A2.5,2.5 0 0,0 3,10.5C3,11.44 3.53,12.25 4.29,12.68C4.65,12.88 5.06,13 5.5,13C5.94,13 6.35,12.88 6.71,12.68C7.08,12.47 7.39,12.17 7.62,11.81C6.89,10.86 6.5,9.7 6.5,8.5C6.5,8.41 6.5,8.31 6.5,8.22C6.2,8.08 5.86,8 5.5,8M18.5,8C18.14,8 17.8,8.08 17.5,8.22C17.5,8.31 17.5,8.41 17.5,8.5C17.5,9.7 17.11,10.86 16.38,11.81C16.5,12 16.63,12.15 16.78,12.3C16.94,12.45 17.1,12.58 17.29,12.68C17.65,12.88 18.06,13 18.5,13C18.94,13 19.35,12.88 19.71,12.68C20.47,12.25 21,11.44 21,10.5A2.5,2.5 0 0,0 18.5,8M12,14C9.66,14 5,15.17 5,17.5V19H19V17.5C19,15.17 14.34,14 12,14M4.71,14.55C2.78,14.78 0,15.76 0,17.5V19H3V17.07C3,16.06 3.69,15.22 4.71,14.55M19.29,14.55C20.31,15.22 21,16.06 21,17.07V19H24V17.5C24,15.76 21.22,14.78 19.29,14.55M12,16C13.53,16 15.24,16.5 16.23,17H7.77C8.76,16.5 10.47,16 12,16Z" />'
	+ '</svg>'

/**
 * A user or group allowed to access the agent.
 * Groups are marked as no-user so the avatar is replaced by a group icon.
 */
const makeUserAccessEntry = (userId, displayName = null) => ({
	id: userId,
	displayName: displayName || userId,
	user: userId,
	type: 'user',
})
const makeGroupAccessEntry = (groupId, displayName = null) => ({
	id: groupId,
	displayName: displayName || groupId,
	isNoUser: true,
	iconSvg: GROUP_ICON_SVG,
	iconName: 'group',
	type: 'group',
})

export default {
	name: 'TalkAgentUsers',

	components: {
		AccountPlusOutlineIcon,
		DeleteOutlineIcon,
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcNoteCard,
		NcRichContenteditable,
		NcSelectUsers,
		NcTextField,
		PencilIcon,
	},

	props: {
		agents: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['agent-created', 'agent-updated', 'agent-deleted'],

	data() {
		return {
			showCreateModal: false,
			showEditModal: false,
			creating: false,
			saving: false,
			deletingAgent: null,
			alsoDeleteUser: false,
			newAgent: { name: '', userId: '', personality: '', scope: '', access: [] },
			editingAgentData: { id: null, display_name: '', personality: '', scope: '', access: [] },
			accessOptions: [],
			searchingAccess: false,
			accessSearchTimeout: null,
		}
	},

	beforeUnmount() {
		clearTimeout(this.accessSearchTimeout)
	},

	methods: {
		/**
		 * Debounced search of the users and groups allowed to access the agent,
		 * through the provisioning API (admin only)
		 */
		onSearchAccess(query) {
			clearTimeout(this.accessSearchTimeout)
			if (!query) {
				this.accessOptions = []
				return
			}
			this.accessSearchTimeout = setTimeout(() => this.searchAccess(query), 200)
		},
		async searchAccess(query) {
			this.searchingAccess = true
			try {
				const [usersResponse, groupsResponse] = await Promise.allSettled([
					axios.get(generateOcsUrl('cloud/users/details?limit=20&search={search}', { search: query })),
					axios.get(generateOcsUrl('cloud/groups/details?limit=20&search={search}', { search: query })),
				])
				if (usersResponse.status === 'rejected') {
					console.error('Failed to search users', usersResponse.reason)
					showError(t('assistant', 'Failed to search users'))
				}
				if (groupsResponse.status === 'rejected') {
					console.error('Failed to search groups', groupsResponse.reason)
					showError(t('assistant', 'Failed to search groups'))
				}
				const users = Object.values(usersResponse.value?.data?.ocs?.data?.users ?? {})
					.map((user) => makeUserAccessEntry(user.id, user.displayname))
				const groups = (groupsResponse.value?.data?.ocs?.data?.groups ?? [])
					.map((group) => makeGroupAccessEntry(group.id, group.displayname))
				this.accessOptions = [...users, ...groups]
			} finally {
				this.searchingAccess = false
			}
		},
		onAgentCreated(agent) {
			if (agent) {
				this.$emit('agent-created', agent)
			}
			this.showCreateModal = false
			this.newAgent = { name: '', userId: '', personality: '', scope: '', access: [] }
		},
		openEditModal(agent) {
			this.editingAgentData = {
				id: agent.id,
				display_name: agent.display_name,
				personality: agent.personality ?? '',
				scope: agent.scope ?? '',
				access: [
					...(agent.access_users ?? []).map((userId) => makeUserAccessEntry(userId)),
					...(agent.access_groups ?? []).map((groupId) => makeGroupAccessEntry(groupId)),
				],
			}
			this.showEditModal = true
			this.fetchAccessDisplayNames()
		},
		/**
		 * Resolve the display names of the users and groups selected in the edit
		 * dialog so the chips show the display name instead of the id
		 */
		async fetchAccessDisplayNames() {
			const entries = this.editingAgentData.access
			const resolved = await Promise.all(entries.map(async (entry) => {
				try {
					if (entry.type === 'group') {
						const response = await axios.get(
							generateOcsUrl('cloud/groups/details?limit=20&search={search}', { search: entry.id }),
						)
						const found = (response.data.ocs.data.groups ?? [])
							.find((group) => group.id === entry.id)
						return makeGroupAccessEntry(entry.id, found?.displayname)
					}
					const response = await axios.get(generateOcsUrl('cloud/users/{userId}', { userId: entry.id }))
					return makeUserAccessEntry(entry.id, response.data.ocs.data.displayname)
				} catch (error) {
					console.error('Failed to load the display name of an allowed user or group', error)
					return entry
				}
			}))
			this.editingAgentData.access = resolved
		},
		accessUserIds(selectedEntries) {
			return (selectedEntries ?? [])
				.filter((entry) => entry.type !== 'group')
				.map((entry) => entry.id)
		},
		accessGroupIds(selectedEntries) {
			return (selectedEntries ?? [])
				.filter((entry) => entry.type === 'group')
				.map((entry) => entry.id)
		},
		async createAgent() {
			this.creating = true
			try {
				const response = await axios.post(agentsUrl(), {
					name: this.newAgent.name,
					userId: this.newAgent.userId,
					personality: this.newAgent.personality,
					scope: this.newAgent.scope,
					accessUsers: this.accessUserIds(this.newAgent.access),
					accessGroups: this.accessGroupIds(this.newAgent.access),
				})
				this.$emit('agent-created', response.data.ocs.data.agent)
				showSuccess(t('assistant', 'Agent created'))
				this.onAgentCreated()
			} catch (error) {
				console.error('Failed to create agent', error)
				showError(t('assistant', 'Failed to create the agent')
					+ ': ' + (error.response?.data?.ocs?.data?.error ?? error.response?.data?.error ?? ''))
			} finally {
				this.creating = false
			}
		},
		async saveAgent() {
			this.saving = true
			try {
				const response = await axios.put(agentUrl(this.editingAgentData.id), {
					personality: this.editingAgentData.personality,
					scope: this.editingAgentData.scope,
					accessUsers: this.accessUserIds(this.editingAgentData.access),
					accessGroups: this.accessGroupIds(this.editingAgentData.access),
				})
				this.$emit('agent-updated', response.data.ocs.data.agent)
				showSuccess(t('assistant', 'Agent saved'))
				this.showEditModal = false
			} catch (error) {
				console.error('Failed to save agent', error)
				showError(t('assistant', 'Failed to update the agent')
					+ ': ' + (error.response?.data?.ocs?.data?.error ?? error.response?.data?.error ?? ''))
			} finally {
				this.saving = false
			}
		},
		onDeleteClicked(agent) {
			this.alsoDeleteUser = false
			this.deletingAgent = agent
		},
		async deleteAgent() {
			this.saving = true
			const agent = this.deletingAgent
			try {
				await axios.delete(agentUrl(agent.id) + `?deleteUser=${this.alsoDeleteUser ? 1 : 0}`)
				this.$emit('agent-deleted', agent)
				showSuccess(t('assistant', 'Agent deleted'))
				this.deletingAgent = null
			} catch (error) {
				console.error('Failed to delete agent', error)
				showError(t('assistant', 'Failed to delete the agent'))
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.talk-agents {
	display: flex;
	flex-direction: column;
	gap: 8px;

	&__list {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	&__row {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 0;
		border-bottom: 1px solid var(--color-border);
	}

	&__info {
		display: flex;
		flex-direction: column;
		flex-grow: 1;
		min-width: 0;
	}

	&__name {
		font-weight: bold;
	}

	&__uid {
		color: var(--color-text-maxcontrast);
		font-size: 0.8em;
	}

	&__personality {
		font-size: 0.85em;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: 500px;
	}

	&__actions {
		display: flex;
		gap: 4px;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
	}

	&__form {
		display: flex;
		flex-direction: column;
		gap: 12px;
		padding: 8px;
	}

	&__select {
		min-width: 300px;
	}

	&__access-hint {
		color: var(--color-text-maxcontrast);
		font-size: 0.85em;
	}
}
</style>
