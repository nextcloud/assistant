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
import NcTextField from '@nextcloud/vue/components/NcTextField'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

const agentsUrl = () => generateOcsUrl('/apps/assistant/api/v1/agents')
const agentUrl = (id) => generateOcsUrl(`/apps/assistant/api/v1/agents/${id}`)

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
			newAgent: { name: '', userId: '', personality: '', scope: '' },
			editingAgentData: { id: null, display_name: '', personality: '', scope: '' },
		}
	},

	methods: {
		onAgentCreated(agent) {
			if (agent) {
				this.$emit('agent-created', agent)
			}
			this.showCreateModal = false
			this.newAgent = { name: '', userId: '', personality: '', scope: '' }
		},
		openEditModal(agent) {
			this.editingAgentData = {
				id: agent.id,
				display_name: agent.display_name,
				personality: agent.personality ?? '',
				scope: agent.scope ?? '',
			}
			this.showEditModal = true
		},
		async createAgent() {
			this.creating = true
			try {
				const response = await axios.post(agentsUrl(), {
					name: this.newAgent.name,
					userId: this.newAgent.userId,
					personality: this.newAgent.personality,
					scope: this.newAgent.scope,
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
}
</style>
