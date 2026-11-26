<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="mcp-servers">
		<h3>{{ t('assistant', 'MCP servers') }}</h3>
		<p>{{ t('assistant', 'Connect services that offer an MCP server, so the assistant can use their tools in chat. The assistant asks you before every use of one of these tools. Only connect services you trust: they see what the assistant sends them and their answers can try to steer the assistant.') }}</p>
		<NcNoteCard v-if="!enabled" type="warning">
			{{ t('assistant', 'Your administrator no longer allows MCP servers. The assistant does not use the servers below; you can still remove them.') }}
		</NcNoteCard>
		<p v-if="servers.length === 0 && editing === null" class="mcp-servers__empty">
			{{ t('assistant', 'No MCP servers yet') }}
		</p>
		<ul v-if="servers.length > 0" class="mcp-servers__list">
			<li v-for="server in servers" :key="server.id" class="mcp-servers__server">
				<div class="mcp-servers__server-text">
					<span>{{ server.name }}</span>
					<span class="mcp-servers__hint">{{ server.url }}</span>
				</div>
				<NcCheckboxRadioSwitch type="switch"
					:model-value="server.enabled"
					:disabled="!enabled || saving"
					:aria-label="t('assistant', 'Use {name}', { name: server.name })"
					@update:model-value="onToggle(server, $event)" />
				<NcButton v-if="enabled"
					variant="tertiary"
					:aria-label="t('assistant', 'Edit {name}', { name: server.name })"
					:disabled="saving || editing !== null"
					@click="startEdit(server)">
					<template #icon>
						<PencilOutlineIcon :size="20" />
					</template>
				</NcButton>
				<NcButton variant="tertiary"
					:aria-label="t('assistant', 'Remove {name}', { name: server.name })"
					:disabled="saving || editing !== null"
					@click="onDelete(server)">
					<template #icon>
						<DeleteOutlineIcon :size="20" />
					</template>
				</NcButton>
			</li>
		</ul>

		<form v-if="editing !== null" class="mcp-servers__form" @submit.prevent="onSave">
			<h4>{{ editing.id === null ? t('assistant', 'Add MCP server') : t('assistant', 'Edit MCP server') }}</h4>
			<NcTextField v-model="editing.name"
				:label="t('assistant', 'Name')"
				:disabled="saving"
				:maxlength="64"
				required />
			<NcTextField v-model="editing.url"
				type="url"
				:label="t('assistant', 'URL')"
				:disabled="saving"
				placeholder="https://example.com/mcp"
				required />
			<p class="mcp-servers__hint">
				{{ t('assistant', 'The address of the server\'s streamable HTTP endpoint. It must use https.') }}
			</p>
			<h5>{{ t('assistant', 'Headers') }}</h5>
			<p class="mcp-servers__hint">
				{{ t('assistant', 'For example "Authorization" with "Bearer" followed by your token. Header values are stored encrypted and cannot be viewed again; leave a stored value empty to keep it, unless you move the server to another host.') }}
			</p>
			<div v-for="(header, index) in editing.headers" :key="index" class="mcp-servers__header">
				<NcTextField v-model="header.name"
					:label="t('assistant', 'Header name')"
					:readonly="header.stored"
					:disabled="saving" />
				<NcPasswordField v-model="header.value"
					:label="t('assistant', 'Value')"
					:placeholder="header.stored ? t('assistant', 'Stored, leave empty to keep') : ''"
					:disabled="saving"
					autocomplete="off" />
				<NcButton variant="tertiary"
					:aria-label="t('assistant', 'Remove header')"
					:disabled="saving"
					@click="editing.headers.splice(index, 1)">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
				</NcButton>
			</div>
			<NcButton variant="tertiary" :disabled="saving" @click="editing.headers.push({ name: '', value: '', stored: false })">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('assistant', 'Add header') }}
			</NcButton>
			<div class="mcp-servers__form-buttons">
				<NcButton variant="primary" type="submit" :disabled="saving">
					{{ t('assistant', 'Save') }}
				</NcButton>
				<NcButton variant="tertiary" :disabled="saving" @click="editing = null">
					{{ t('assistant', 'Cancel') }}
				</NcButton>
			</div>
		</form>
		<NcButton v-else-if="enabled && servers.length < maxServers"
			variant="secondary"
			@click="startEdit(null)">
			<template #icon>
				<PlusIcon :size="20" />
			</template>
			{{ t('assistant', 'Add MCP server') }}
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import CloseIcon from 'vue-material-design-icons/Close.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

const SERVERS_URL = '/apps/assistant/api/v1/mcp-servers'

export default {
	name: 'McpServersSettings',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcPasswordField,
		NcTextField,
		CloseIcon,
		DeleteOutlineIcon,
		PencilOutlineIcon,
		PlusIcon,
	},

	props: {
		initialState: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			enabled: this.initialState.enabled,
			servers: this.initialState.servers,
			maxServers: this.initialState.maxServers,
			// the server being added (id null) or edited, or null
			editing: null,
			saving: false,
		}
	},

	methods: {
		startEdit(server) {
			this.editing = server === null
				? { id: null, name: '', url: '', headers: [] }
				: {
						id: server.id,
						name: server.name,
						url: server.url,
						headers: server.headers.map(name => ({ name, value: '', stored: true })),
					}
		},
		async onSave() {
			// no prototype, so that a header named __proto__ is a plain key
			const headers = Object.create(null)
			for (const header of this.editing.headers) {
				const name = header.name.trim()
				if (name === '') {
					continue
				}
				// null keeps the stored value, which the browser never gets to see
				headers[name] = header.stored && header.value === '' ? null : header.value
			}
			const body = { name: this.editing.name, url: this.editing.url, headers }
			const server = this.editing.id === null
				? await this.request('post', SERVERS_URL, body)
				: await this.request('put', `${SERVERS_URL}/${this.editing.id}`, body)
			if (server) {
				this.replaceServer(server)
				this.editing = null
				showSuccess(t('assistant', 'MCP server saved. It can take a minute until the assistant uses the change.'))
			}
		},
		async onToggle(server, enabled) {
			const updated = await this.request('put', `${SERVERS_URL}/${server.id}`, { enabled })
			if (updated) {
				this.replaceServer(updated)
			}
		},
		async onDelete(server) {
			if (await this.request('delete', `${SERVERS_URL}/${server.id}`) !== undefined) {
				this.servers = this.servers.filter(s => s.id !== server.id)
				showSuccess(t('assistant', 'MCP server removed'))
			}
		},
		replaceServer(server) {
			const index = this.servers.findIndex(s => s.id === server.id)
			if (index === -1) {
				this.servers.push(server)
			} else {
				this.servers.splice(index, 1, server)
			}
		},
		/**
		 * Send a request to the MCP servers API
		 *
		 * @param {string} method HTTP method
		 * @param {string} path path below the OCS root
		 * @param {object|undefined} body request body
		 * @return {Promise<object|null|undefined>} the server in the response, null if there is none, undefined on error
		 */
		async request(method, path, body = undefined) {
			this.saving = true
			try {
				const response = await axios({ method, url: generateOcsUrl(path), data: body })
				return response.data.ocs.data.server ?? null
			} catch (error) {
				showError(error.response?.data?.ocs?.data?.error ?? t('assistant', 'Failed to save the MCP server'))
				return undefined
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.mcp-servers {
	max-width: 800px;

	&__empty,
	&__hint {
		color: var(--color-text-maxcontrast);
	}

	&__server,
	&__header,
	&__form-buttons {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__list {
		margin-bottom: 8px;
	}

	&__server-text {
		display: flex;
		flex-direction: column;
		flex: 1;
		min-width: 0;
		overflow-wrap: anywhere;
	}

	&__form {
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin-top: 12px;
	}
}
</style>
