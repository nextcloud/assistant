<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="input-area">
		<NcRichContenteditable ref="richContenteditable"
			:class="{ 'input-area__thinking': loading.llmGeneration }"
			:model-value="chatContent"
			:auto-complete="() => {}"
			:link-auto-complete="false"
			:disabled="disabled"
			:placeholder="loading.llmGeneration ? thinkingText : placeholderText"
			:aria-label="loading.llmGeneration ? thinkingText : placeholderText"
			:maxlength="1600"
			:multiline="isMobile"
			dir="auto"
			@update:model-value="$emit('update:chatContent', $event)"
			@submit="$emit('submit', $event)" />
		<div class="input-area__button-box">
			<NcButton class="input-area__button-box__button"
				:aria-label="submitBtnAriaText"
				:disabled="disabled || !chatContent.trim()"
				variant="primary"
				@click="$emit('submit', $event)">
				<template #icon>
					<SendIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import SendIcon from 'vue-material-design-icons/Send.vue'

import isMobile from '../../mixins/isMobile.js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'

/*
maxlength calculation (just a rough estimate):
- 1600 characters
- ~400 words
- ~300 tokens
*/

export default {
	name: 'InputArea',

	components: {
		SendIcon,

		NcButton,
		NcRichContenteditable,
	},

	mixins: [
		isMobile,
	],

	props: {
		chatContent: {
			type: String,
			required: true,
		},
		loading: {
			type: Object,
			default: () => ({
				initialMessages: false,
				olderMessages: false,
				llmGeneration: false,
				titleGeneration: false,
				newHumanMessage: false,
				newSession: false,
				messageDelete: false,
				sessionDelete: false,
			}),
		},
	},

	emits: ['update:chatContent'],

	data: () => {
		return {
			placeholderText: t('assistant', 'Type a message…'),
			thinkingText: t('assistant', 'Processing…'),
			submitBtnAriaText: t('assistant', 'Submit'),
		}
	},

	computed: {
		disabled() {
			return this.loading.llmGeneration || this.loading.olderMessages || this.loading.initialMessages || this.loading.titleGeneration || this.loading.newHumanMessage || this.loading.newSession
		},
	},

	mounted() {
		this.focus()
	},

	methods: {
		focus() {
			this.$nextTick(() => {
				this.$refs.richContenteditable.focus()
			})
		},
	},
}
</script>

<style lang="scss">
[id$='-tribute'][id*='nc-rich-contenteditable-'][role='listbox'] {
	z-index: 9999;
}
</style>

<style lang="scss" scoped>
:deep(.rich-contenteditable) {
	width: 100% !important;

	.rich-contenteditable__input {
		// TODO or fix in nc/vue
		padding-top: 4px !important;
		padding-bottom: 4px !important;

		min-height: var(--default-clickable-area) !important;
		line-height: 22px !important;
	}
}

:deep(.rich-contenteditable__input--disabled) {
	border-radius: var(--border-radius-large) !important;
	cursor: default !important;
}

.input-area {
	display: flex;
	flex-direction: row;
	justify-content: space-between;
	align-items: end;
	gap: 4px;

	:deep(&__thinking > div) {
		font-style: italic;
		animation: breathing 2s linear infinite normal;
	}

	&__button-box {
		display: flex;
		flex-direction: column;
		justify-content: end;

		&__button {
			height: fit-content;
		}
	}
}

@keyframes breathing {
	0% {
		border-color: var(--color-main-text);
	}
	50% {
		border-color: var(--color-border-maxcontrast);
	}
	100% {
		border-color: var(--color-main-text);
	}
}
</style>
