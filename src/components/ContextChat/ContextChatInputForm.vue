<template>
	<div class="scoped-cc-input-form">
		<div class="line spaced">
			<div class="radios">
				<NcCheckboxRadioSwitch
					type="radio"
					:checked="scopeType"
					:value="ScopeType.SOURCE"
					:button-variant="true"
					button-variant-grouped="horizontal"
					name="scopeType"
					@update:checked="(value) => (scopeType = value)">
					{{ tStrings[ScopeType.SOURCE] }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					type="radio"
					:checked="scopeType"
					:value="ScopeType.PROVIDER"
					:button-variant="true"
					button-variant-grouped="horizontal"
					name="scopeType"
					@update:checked="(value) => (scopeType = value)">
					{{ tStrings[ScopeType.PROVIDER] }}
				</NcCheckboxRadioSwitch>
			</div>
			<NcButton
				type="secondary"
				:disabled="scopeListMeta.length === 0"
				@click="clearSelection">
				<template #icon>
					<PlaylistRemoveIcon />
				</template>
				{{ tStrings['Clear Selection'] }}
			</NcButton>
		</div>
		<div class="selector-form">
			<div v-if="scopeType === ScopeType.SOURCE" class="sources-form">
				<NcButton
					type="secondary"
					@click="onChooseSourceClicked">
					<template #icon>
						<FileDocumentIcon />
					</template>
					{{ tStrings['Choose Files/Folders'] }}
				</NcButton>
				<NcSelect v-if="scopeListMeta.length > 0"
					v-model="scopeListMeta"
					class="line"
					:placeholder="tStrings[ScopeType.SOURCE]"
					:multiple="true"
					:close-on-select="false"
					:dropdown-should-open="dropdownShouldNotOpen"
					:label-outside="true"
					:no-wrap="false">
					<template #selected-option="option">
						<NcAvatar
							:size="24"
							:url="getFilePreviewUrl(option.type === 'file' ? option.id : null)"
							:display-name="option.label" />
						<span class="multiselect-name">
							{{ option.label }}
						</span>
					</template>
				</NcSelect>
			</div>
			<div v-else class="providers-form">
				<NcSelect
					v-model="scopeListMeta"
					:placeholder="tStrings[ScopeType.PROVIDER]"
					:multiple="true"
					:close-on-select="false"
					:no-wrap="false"
					:loading="providersLoading"
					:label-outside="true"
					:append-to-body="false"
					:options="options">
					<template #option="option">
						<div class="select-option">
							<NcAvatar
								:size="24"
								:url="option.icon"
								:display-name="option.label" />
							<span class="multiselect-name">
								{{ option.label }}
							</span>
						</div>
					</template>
					<template #selected-option="option">
						<div class="select-option">
							<NcAvatar
								:size="24"
								:url="option.icon"
								:display-name="option.label" />
							<span class="multiselect-name">
								{{ option.label }}
							</span>
						</div>
					</template>
				</NcSelect>
			</div>
		</div>
	</div>
</template>

<script>
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import PlaylistRemoveIcon from 'vue-material-design-icons/PlaylistRemove.vue'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

const _ScopeType = Object.freeze({
	SOURCE: 'source',
	PROVIDER: 'provider',
})

const _tStrings = {
	[_ScopeType.SOURCE]: t('assistant', 'Select Files/Folders'),
	[_ScopeType.PROVIDER]: t('assistant', 'Select Providers'),
	'Choose Files/Folders': t('assistant', 'Choose Files/Folders'),
	Choose: t('assistant', 'Choose'),
	'Clear Selection': t('assistant', 'Clear Selection'),
}

const SUPPORTED_MIMETYPES = [
	'text/plain',
	'text/markdown',
	'application/json',
	'application/pdf',
	'text/csv',
	'application/epub+zip',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/vnd.ms-powerpoint',
	'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'application/vnd.oasis.opendocument.spreadsheet',
	'application/vnd.ms-excel.sheet.macroEnabled.12',
	'application/vnd.oasis.opendocument.text',
	'text/rtf',
	'text/x-rst',
	'application/xml',
	'message/rfc822',
	'application/vnd.ms-outlook',
	'text/org',
	// folders
	'httpd/unix-directory',
]

const picker = (callback) => getFilePickerBuilder(_tStrings[_ScopeType.SOURCE])
	.setMimeTypeFilter(SUPPORTED_MIMETYPES)
	.setMultiSelect(true)
	.allowDirectories(true)
	.addButton({
		id: 'choose-ff',
		label: _tStrings.Choose,
		type: 'primary',
		callback,
	})
	.build()

export default {
	name: 'ContextChatInputForm',

	components: {
		FileDocumentIcon,
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		PlaylistRemoveIcon,
	},

	props: {
		sccData: {
			type: Object,
			default: () => ({
				sccScopeType: _ScopeType.SOURCE,
				sccScopeList: [],
				sccScopeListMeta: [],
			}),
			validator: (value) => {
				return Object.values(_ScopeType).includes(value?.sccScopeType)
			},
		},
	},

	data() {
		return {
			ScopeType: _ScopeType,
			tStrings: _tStrings,

			options: [],
			providersLoading: false,
			defaultProviderKey: 'files__default',
		}
	},

	computed: {
		scopeType: {
			get() {
				return this.sccData.sccScopeType
			},

			set(value) {
				console.debug('Setting scope type:', value)
				if (value === this.ScopeType.PROVIDER && this.options.length === 0) {
					this.fetchProviders()
				}

				this.$emit('update:sccData', {
					sccScopeType: value,
					sccScopeList: [],
					sccScopeListMeta: [],
				})
			},
		},
		scopeListMeta: {
			get() {
				if (!this.sccData.sccScopeListMeta) {
					return []
				}
				return this.sccData.sccScopeListMeta
			},

			set(value) {
				console.debug('Setting scope list meta:', value)
				this.$emit('update:sccData', {
					sccScopeType: this.scopeType,
					sccScopeList: value?.map((item) => item.id) ?? [],
					sccScopeListMeta: value ?? [],
				})
			},
		},
	},

	mounted() {
		const defaultProviderUrl = generateUrl('/apps/context_chat/default-provider-key')
		axios.get(defaultProviderUrl)
			.then((response) => (this.defaultProviderKey = response.data ? response.data : this.defaultProviderKey))
			.catch((error) => {
				console.error('Error fetching default provider key:', error)
				showError(t('assistant', 'Error fetching default provider key'))
			})
	},

	methods: {
		dropdownShouldNotOpen() {
			return false
		},
		onChooseSourceClicked() {
			picker(this.chooseDialogCallback).pick()
		},
		isScopePresent(scopeId) {
			return this.scopeListMeta.some((item) => item.id === scopeId)
		},
		chooseDialogCallback(nodes) {
			console.debug('nodes:', nodes)
			const scopeListMeta = []
			for (const node of nodes) {
				const scopeId = `${this.defaultProviderKey}: ${node.fileid}`
				if (node.path && !this.isScopePresent(scopeId)) {
					scopeListMeta.push({
						id: scopeId,
						type: node.type,
						label: (node.path?.substring(0, 100) + (node.path.length > 100 ? '...' : '')) || node.name,
						isNoUser: true,
					})
				}
			}

			this.scopeListMeta = this.scopeListMeta.concat(scopeListMeta)
		},
		fetchProviders() {
			this.providersLoading = true
			axios.get(generateUrl('/apps/context_chat/providers'))
				.then((response) => (this.options = response.data))
				.catch((error) => {
					console.error('Error fetching providers:', error)
					showError(t('assistant', 'Error fetching providers'))
				})
				.finally(() => (this.providersLoading = false))
		},
		clearSelection() {
			this.scopeListMeta = []
		},
		getFilePreviewUrl(fileId) {
			if (fileId == null) {
				return generateUrl('/apps/theming/img/core/filetypes/folder.svg')
			}
			return generateUrl(
				'/apps/assistant/preview?id={fileId}&x=24&y=24',
				{ fileId: fileId.substring(`${this.defaultProviderKey}: `.length) },
			)
		},
	},
}
</script>

<style lang="scss" scoped>
.scoped-cc-input-form {
	padding: 12px;

	.line {
		display: flex;
		flex-direction: row;
		align-items: start;
		margin-top: 8px;
		width: 100%;
	}

	.spaced {
		justify-content: space-between;
		align-items: center;
	}

	.radios {
		display: flex;

		:deep(.checkbox-radio-switch__text) {
			flex: unset !important;
		}
	}

	.selector-form {
		margin-top: 16px;
		:deep .avatardiv {
			border-radius: 50%;

			&> img {
				border-radius: 0 !important;
			}
		}

		.providers-form {
			.v-select {
				min-width: 400px;
			}

			:deep .avatardiv>img {
				filter: var(--background-invert-if-dark) !important;
			}
		}

		.sources-form {
			min-width: 400px;

			:deep .vs__actions {
				display: none !important;
			}
		}
	}

	.select-option {
		display: flex;
		align-items: center;
	}

	.multiselect-name {
		margin-left: 8px;
	}
}
</style>
