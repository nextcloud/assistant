<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="message.content || message.reasoning || streamedMessageContent || parsedSources.length || hasAttachments"
		class="message"
		@mouseover="showMessageActions = true"
		@mouseleave="showMessageActions = false">
		<MessageActions v-show="showMessageActions"
			class="message__actions"
			:show-regenerate="showRegenerate"
			:delete-loading="deleteLoading"
			:regenerate-loading="regenerateLoading"
			@copy="copyMessage(message.content)"
			@regenerate="$emit('regenerate')"
			@delete="$emit('delete')" />
		<div class="message__header">
			<div class="message__header__role">
				<!-- we change the user prop when newMessageLoading changes to re-render the avatar without the icon template -->
				<NcAvatar
					:user="message.role === 'human' ? (newMessageLoading ? '' : userId) : 'Nextcloud Assistant'"
					:display-name="message.role === 'human' ? displayName : t('assistant', 'Nextcloud Assistant')"
					:is-no-user="message.role === 'assistant'"
					:hide-status="true">
					<template v-if="(message.role === 'human' && newMessageLoading) || message.role === 'assistant'" #icon>
						<NcLoadingIcon v-if="(message.role === 'human' && newMessageLoading) || streaming" :size="32" />
						<AssistantIcon v-else-if="message.role === 'assistant'" :size="20" />
					</template>
				</NcAvatar>
				<div class="message__header__role__name">
					{{ message.role === 'human' ? displayName : t('assistant', 'Nextcloud Assistant') }}
				</div>
				<div style="display: flex; gap: 5px">
					<NcPopover v-if="message.reasoning">
						<template #trigger>
							<NcButton
								:aria-label="t('assistant', 'Reasoning content')"
								:title="t('assistant', 'Reasoning content')">
								<template #icon>
									<ReasoningContentIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<template #default>
							<div class="reasoningcontent_popover_inner">
								<h6>{{ t('assistant', 'Reasoning content') }}</h6>
								<NcRichText :text="message.reasoning"
									:use-markdown="true"
									:autolink="true" />
							</div>
						</template>
					</NcPopover>
					<NcPopover v-if="parsedSources.length && !streaming">
						<template #trigger>
							<NcButton
								:aria-label="t('assistant', 'Information sources & actions')"
								:title="t('assistant', 'Information sources & actions')">
								<template #icon>
									<ToolInformationIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<template #default>
							<div class="toolinfo_popover_inner">
								<h6>{{ t('assistant', 'Information sources & actions') }}</h6>
								<ul>
									<li v-for="source in parsedSources" :key="source">
										{{ source }}
									</li>
								</ul>
							</div>
						</template>
					</NcPopover>
				</div>
			</div>
			<NcDateTime class="message__header__timestamp" :timestamp="new Date((message?.timestamp ?? 0) * 1000)" :ignore-seconds="true" />
		</div>
		<div v-if="streaming && !streamedMessageContent" class="message__streamed-reasoning">
			<NcChip :text="t('assistant', 'Reasoning…')"
				no-close
				:variant="!parsedSources.length ? 'primary' : 'secondary'"
				style="display: block; margin-bottom: 0.5em;" />
		</div>
		<div v-if="streaming" class="message__streamed-sources">
			<NcChip v-for="(source, index) in parsedSources"
				:key="source"
				:text="source"
				no-close
				:variant="index === parsedSources.length-1 ? 'primary' : 'secondary'"
				style="display: block; margin-bottom: 0.5em;" />
		</div>
		<NcRichText class="message__content"
			:text="streaming ? streamedMessageContent : message.content"
			:use-markdown="true"
			:reference-limit="1"
			:references="references"
			:autolink="true" />
		<AudioDisplay v-for="a in audioAttachments"
			:key="a.type + '-' + a.file_id"
			class="message__content"
			:autoplay="message.autoPlay"
			:file-id="a.file_id"
			:task-id="message.role === 'human' ? undefined : (a.ocp_task_id ?? message.ocp_task_id)"
			:is-output="isOutput" />
		<div v-if="fileAttachments.length" class="message__content message__files">
			<FileDisplay v-for="f in fileAttachments"
				:key="f.type + '-' + f.file_id"
				:file-id="f.file_id"
				:task-id="message.role === 'human' ? undefined : (f.ocp_task_id ?? message.ocp_task_id)"
				:is-output="isOutput"
				:clickable="true"
				@click.native="onPreviewClick(f)" />
		</div>
	</div>
</template>

<script>
import AssistantIcon from '../icons/AssistantIcon.vue'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import { NcRichText } from '@nextcloud/vue/components/NcRichText'

import MessageActions from './MessageActions.vue'
import AudioDisplay from '../fields/AudioDisplay.vue'
import { ReasoningContentIcon, ToolInformationIcon } from '../icons/aliases.js'

import { getCurrentUser } from '@nextcloud/auth'
import { showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { SHAPE_TYPE_NAMES } from '../../constants.js'
import FileDisplay from '../fields/FileDisplay.vue'

const PLAIN_URL_PATTERN = /(?:\s|^|\()((?:https?:\/\/)(?:[-A-Z0-9+_.]+(?::[0-9]+)?(?:\/[-A-Z0-9+&@#%?=~_|!:,.;()]*)*))(?:\s|$|\))/ig
const MARKDOWN_LINK_PATTERN = /\[[-A-Z0-9+&@#%?=~_|!:,.;()]+\]\(((?:https?:\/\/)(?:[-A-Z0-9+_.]+(?::[0-9]+)?(?:\/[-A-Z0-9+&@#%?=~_|!:,.;]*)*))\)/ig

export default {
	name: 'Message',

	components: {
		AudioDisplay,
		FileDisplay,
		AssistantIcon,

		NcAvatar,
		NcDateTime,
		NcLoadingIcon,
		NcRichText,
		NcPopover,
		NcButton,
		ReasoningContentIcon,
		ToolInformationIcon,

		MessageActions,
		NcChip,
	},

	props: {
		// { id: number, session_id: number, role: string, content: string, timestamp: number, sources: string, reasoning: string }
		message: {
			type: Object,
			required: true,
		},
		showRegenerate: {
			type: Boolean,
			default: false,
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
		streaming: {
			type: Boolean,
			default: false,
		},
		informationSourceNames: {
			type: Object,
			default: null,
		},
	},

	emits: ['delete', 'regenerate'],

	data: () => {
		return {
			displayName: getCurrentUser()?.displayName ?? getCurrentUser()?.uid ?? t('assistant', 'You'),
			userId: getCurrentUser()?.uid ?? t('assistant', 'You'),
			showMessageActions: false,
			// we could initialize this with undefined but then NcRichText will try to find links
			// and resolve them before we had a chance to do so, resulting in unnecessary requests to references/resolve
			// with [] we inhibit the extract+resolve mechanism on NcRichText
			// TODO This can be removed (and all the custom extract+resolve logic) when fixed in NcRichText
			references: [],
			streamedMessageContent: '',
		}
	},

	computed: {
		parsedSources() {
			if (!this.message.sources || ['', '[]'].includes(this.message.sources)) {
				return []
			}
			let parsedSources = JSON.parse(this.message.sources)
			parsedSources = parsedSources.map((source) => this.getSourceString(source))
			return [...new Set(parsedSources)]
		},
		hasAttachments() {
			return this.message.attachments?.length > 0
		},
		audioAttachments() {
			return this.message.attachments?.filter(a => a.type === SHAPE_TYPE_NAMES.Audio) ?? []
		},
		fileAttachments() {
			return this.message.attachments?.filter(a => a.type === SHAPE_TYPE_NAMES.File) ?? []
		},
		isOutput() {
			return this.message.role === 'assistant'
		},
	},

	watch: {
		// Pseudo streaming
		async 'message.content'(messageContent, oldMessageContent) {
			if (!this.streaming) {
				return
			}
			if (oldMessageContent) {
				this.streamedMessageContent = oldMessageContent
				messageContent = messageContent.replace(oldMessageContent, '')
			}
			let cachedStreamedMessageContent
			for (const char of messageContent.split('')) {
				this.streamedMessageContent += char
				cachedStreamedMessageContent = this.streamedMessageContent
				await new Promise(resolve => setTimeout(resolve, 5))
				if (cachedStreamedMessageContent !== this.streamedMessageContent) {
					break
				}
			}
		},
	},

	mounted() {
		this.fetch()
	},

	methods: {
		copyMessage(message) {
			navigator.clipboard.writeText(message)
			showSuccess(t('assistant', 'Message copied to clipboard'))
		},
		fetch() {
			if (!this.message.content) {
				return
			}
			const urlMatch = (new RegExp(PLAIN_URL_PATTERN).exec(this.message.content.trim()))
			const mdMatch = (new RegExp(MARKDOWN_LINK_PATTERN).exec(this.message.content.trim()))
			const firstMatch = urlMatch
				? urlMatch[1].replaceAll(/[).,:;!?]+$/g, '')
				: mdMatch
					? mdMatch[1]
					: false
			if (firstMatch) {
				axios.get(generateOcsUrl('references/resolve') + `?reference=${encodeURIComponent(firstMatch)}`)
					.then((response) => {
						this.references = Object.values(response.data.ocs.data.references)
					})
					.catch((error) => {
						console.error('Failed to extract references', error)
					})
			}
		},
		getSourceString(source) {
			if (source.startsWith('mcp_')) {
				return t('assistant', 'MCP server: {tool_id}', { tool_id: source.substring('mcp_'.length) })
			}
			return this.informationSourceNames[source] ? this.informationSourceNames[source] : source
		},
		toViewerPath(path) {
			// `/userId/files/foo` -> `/foo` (Viewer expects a user-files-relative path)
			const match = /^\/[^/]+\/files(\/.*)$/.exec(path)
			return match ? match[1] : path
		},
		onPreviewClick(file) {
			if (file.file_id === null) {
				return
			}

			if (this.isOutput) {
				const url = generateOcsUrl('/apps/assistant/api/v1/task/{taskId}/file/{fileId}/save', {
					taskId: file.ocp_task_id ?? this.message.ocp_task_id,
					fileId: file.file_id,
				})
				return axios.post(url).then(response => {
					const savedPath = response.data.ocs.data.path
					console.debug('[assistant] view output file', savedPath)
					OCA.Viewer.open({ path: savedPath })
				}).catch(error => {
					console.error(error)
				})
			}

			const url = generateOcsUrl('/apps/assistant/api/v1/file/{fileId}/info', { fileId: file.file_id })
			return axios.get(url).then(response => {
				const path = this.toViewerPath(response.data.ocs.data.path)
				console.debug('[assistant] view input file', path)
				OCA.Viewer.open({ path })
			}).catch(error => {
				console.error(error)
			})
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
		flex-wrap: wrap;
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

			:deep(.assistant-icon) {
				height: 100%;
			}
		}

		&__timestamp {
			color: var(--color-text-maxcontrast);
		}
	}

	&__streamed-sources,
	&__streamed-reasoning {
		margin-left: 2.6em;
	}

	&__content {
		margin-left: 2.6em;
		overflow: auto;

		:deep(ol) {
			margin-left: 1em;
		}

		:deep(.widget-default), :deep(.widget-custom) {
			width: auto !important;
		}
	}

	&__files {
		display: flex;
		flex-direction: row;
		flex-wrap: wrap;
		gap: 0.5em;
	}
}
</style>

<style lang="scss">
.toolinfo_popover_inner,
.reasoningcontent_popover_inner {
	margin: 12px;
	h6 {
		margin: 2px;
	}
	ul {
		list-style-type: disc;
		padding-left: 18px;
	}
}

.reasoningcontent_popover_inner {
	max-width: 500px;
	max-height: 400px;
	overflow-y: auto;
}
</style>
