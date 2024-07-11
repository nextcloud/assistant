<template>
	<div class="cc-input-form">
		<TextInput
			:value="inputs.prompt"
			:label="taskType.inputShape?.prompt?.name"
			:placeholder="taskType.inputShape?.prompt?.description"
			:title="taskType.inputShape?.prompt?.description"
			:is-output="false"
			@update:value="onInputsChanged({ prompt: $event })" />
		<NcCheckboxRadioSwitch :checked.sync="sccEnabled" @update:checked="onUpdateSccEnabled">
			{{ t('assistant', 'Selective context') }}
		</NcCheckboxRadioSwitch>
		<div v-if="sccEnabled" class="line spaced">
			<div class="radios">
				<NcCheckboxRadioSwitch
					type="radio"
					:checked="inputs.scopeType"
					:value="ScopeType.SOURCE"
					:button-variant="true"
					button-variant-grouped="horizontal"
					name="scopeType"
					@update:checked="onScopeTypeChanged(ScopeType.SOURCE)">
					{{ tStrings[ScopeType.SOURCE] }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					type="radio"
					:checked="inputs.scopeType"
					:value="ScopeType.PROVIDER"
					:button-variant="true"
					button-variant-grouped="horizontal"
					name="scopeType"
					@update:checked="onScopeTypeChanged(ScopeType.PROVIDER)">
					{{ tStrings[ScopeType.PROVIDER] }}
				</NcCheckboxRadioSwitch>
			</div>
			<NcButton
				type="secondary"
				:disabled="scopeListMetaArray.length === 0"
				@click="onInputsChanged({ scopeListMeta: '[]'})">
				<template #icon>
					<PlaylistRemoveIcon />
				</template>
				{{ tStrings['Clear Selection'] }}
			</NcButton>
		</div>
		<div v-if="sccEnabled" class="selector-form">
			<div v-if="inputs.scopeType === ScopeType.SOURCE" class="sources-form">
				<NcButton
					type="secondary"
					@click="onChooseSourceClicked">
					<template #icon>
						<FileDocumentIcon />
					</template>
					{{ tStrings['Choose Files/Folders'] }}
				</NcButton>
				<NcSelect v-if="scopeListMetaArray.length > 0"
					:value="scopeListMetaArray"
					class="line"
					:placeholder="tStrings[ScopeType.SOURCE]"
					:multiple="true"
					:close-on-select="false"
					:dropdown-should-open="() => false"
					:label-outside="true"
					:no-wrap="false"
					@input="onScopeListMetaChange">
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
					:value="scopeListMetaArray"
					:placeholder="tStrings[ScopeType.PROVIDER]"
					:multiple="true"
					:close-on-select="false"
					:no-wrap="false"
					:loading="providersLoading"
					:label-outside="true"
					:append-to-body="false"
					:options="providerOptions"
					@input="onScopeListMetaChange">
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

import TextInput from '../fields/TextInput.vue'

import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

const _ScopeType = Object.freeze({
	NONE: 'none',
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
		TextInput,
		FileDocumentIcon,
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		PlaylistRemoveIcon,
	},

	props: {
		inputs: {
			type: Object,
			required: true,
		},
		taskType: {
			type: Object,
			required: true,
		},
	},

	emits: ['update:inputs'],

	data() {
		return {
			ScopeType: _ScopeType,
			tStrings: _tStrings,

			providerOptions: [],
			providersLoading: false,
			defaultProviderKey: 'files__default',

			sccEnabled: !!this.inputs.scopeType && this.inputs.scopeType !== _ScopeType.NONE && !!this.inputs.scopeList,
		}
	},

	computed: {
		scopeListMetaArray() {
			if (!this.inputs.scopeListMeta) {
				return []
			}
			try {
				return JSON.parse(this.inputs.scopeListMeta)
			} catch (error) {
				console.error('failed to parse scopeListMeta', error)
				return []
			}
		},
	},

	mounted() {
		const defaultProviderUrl = generateUrl('/apps/context_chat/default-provider-key')
		axios.get(defaultProviderUrl)
			.then((response) => {
				this.defaultProviderKey = response.data
					? response.data
					: this.defaultProviderKey
			})
			.catch((error) => {
				console.error('Error fetching default provider key:', error)
				showError(t('assistant', 'Error fetching default provider key'))
			})

		// initialize the inputs if necessary
		if (Object.keys(this.inputs).length === 0) {
			this.$emit('update:inputs', {
				prompt: '',
				scopeType: _ScopeType.NONE,
				scopeList: [],
				scopeListMeta: '[]',
			})
		}
	},

	methods: {
		onChooseSourceClicked() {
			picker(this.chooseDialogCallback).pick()
		},
		isScopePresent(scopeId) {
			return this.scopeListMetaArray.some((item) => item.id === scopeId)
		},
		chooseDialogCallback(nodes) {
			console.debug('nodes:', nodes)
			const addedScopeListMeta = []
			for (const node of nodes) {
				const scopeId = `${this.defaultProviderKey}: ${node.fileid}`
				if (node.path && !this.isScopePresent(scopeId)) {
					addedScopeListMeta.push({
						id: scopeId,
						type: node.type,
						label: (node.path?.substring(0, 100) + (node.path.length > 100 ? '...' : '')) || node.name,
						isNoUser: true,
					})
				}
			}

			const newScopeListMetaArray = this.scopeListMetaArray.concat(addedScopeListMeta)
			this.onInputsChanged({
				scopeListMeta: JSON.stringify(newScopeListMetaArray),
				scopeList: newScopeListMetaArray.map(item => item.id),
			})
		},
		fetchProviders() {
			this.providersLoading = true
			axios.get(generateUrl('/apps/context_chat/providers'))
				.then((response) => {
					this.providerOptions = response.data
				})
				.catch((error) => {
					console.error('Error fetching providers:', error)
					showError(t('assistant', 'Error fetching providers'))
				})
				.finally(() => {
					this.providersLoading = false
				})
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
		onUpdateSccEnabled(enabled) {
			this.$emit('update:inputs', {
				prompt: this.inputs.prompt,
				scopeType: enabled ? _ScopeType.SOURCE : _ScopeType.NONE,
				scopeList: [],
				scopeListMeta: '[]',
			})
		},
		onScopeTypeChanged(value) {
			if (value === this.ScopeType.PROVIDER && this.providerOptions.length === 0) {
				this.fetchProviders()
			}

			this.onInputsChanged({
				scopeType: value,
				scopeList: [],
				scopeListMeta: '[]',
			})
		},
		onScopeListMetaChange(value) {
			try {
				this.onInputsChanged({ scopeListMeta: JSON.stringify(value) })
			} catch (error) {
				console.error('Failed to change scopeListMeta', error)
			}
		},
		onInputsChanged(changedInputs) {
			this.$emit('update:inputs', {
				...this.inputs,
				...changedInputs,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.cc-input-form {
	display: flex;
	flex-direction: column;
	gap: 12px;

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
