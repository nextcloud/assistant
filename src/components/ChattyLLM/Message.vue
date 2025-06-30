<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="message.content"
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
				<NcAvatar
					:user="message.role === 'human' ? userId : 'Nextcloud Assistant'"
					:display-name="message.role === 'human' ? displayName : t('assistant', 'Nextcloud Assistant')"
					:is-no-user="message.role === 'assistant'"
					:hide-status="true">
					<template #icon>
						<NcLoadingIcon v-if="message.role === 'human' && newMessageLoading" :size="20" />
						<AssistantIcon v-else-if="message.role === 'assistant'" :size="20" />
					</template>
				</NcAvatar>
				<div class="message__header__role__name">
					{{ message.role === 'human' ? displayName : t('assistant', 'Nextcloud Assistant') }}
				</div>
				<div style="display: flex">
					<NcPopover v-if="parsedSources.length">
						<template #trigger>
							<NcButton
								:aria-label="t('assistant', 'Information sources')">
								<template #icon>
									<InformationBox :size="20" />
								</template>
							</NcButton>
						</template>
						<template #default>
							<div class="toolinfo_popover_inner">
								<h6> Information sources </h6>
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
		<NcRichText class="message__content"
			:text="message.content"
			:use-markdown="true"
			:reference-limit="1"
			:references="references"
			:autolink="true" />
	</div>
</template>

<script>
import AssistantIcon from '../icons/AssistantIcon.vue'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcButton from '@nextcloud/vue/components/NcButton'
import { NcRichText } from '@nextcloud/vue/components/NcRichText'

import InformationBox from 'vue-material-design-icons/InformationBox.vue'

import MessageActions from './MessageActions.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

const PLAIN_URL_PATTERN = /(?:\s|^|\()((?:https?:\/\/)(?:[-A-Z0-9+_.]+(?::[0-9]+)?(?:\/[-A-Z0-9+&@#%?=~_|!:,.;()]*)*))(?:\s|$|\))/ig
const MARKDOWN_LINK_PATTERN = /\[[-A-Z0-9+&@#%?=~_|!:,.;()]+\]\(((?:https?:\/\/)(?:[-A-Z0-9+_.]+(?::[0-9]+)?(?:\/[-A-Z0-9+&@#%?=~_|!:,.;]*)*))\)/ig

export default {
	name: 'Message',

	components: {
		AssistantIcon,

		NcAvatar,
		NcDateTime,
		NcLoadingIcon,
		NcRichText,
		NcPopover,
		NcButton,
		InformationBox,

		MessageActions,
	},

	props: {
		// { id: number, session_id: number, role: string, content: string, timestamp: number, sources: string }
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
		informationSourceNames: {
			type: Array,
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
			return this.informationSourceNames[source] ? this.informationSourceNames[source] : source
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
}
</style>

<style lang="scss">
.toolinfo_popover_inner {
	margin: 12px;
	h6 {
		margin: 2px;
	}
	ul {
		list-style-type: disc;
		padding-left: 18px;
	}
}
</style>
