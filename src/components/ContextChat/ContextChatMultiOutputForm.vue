<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cc-multi-output">
		<div v-for="(block, i) in qaBlocks"
			:key="'qa-block-' + i"
			class="cc-multi-output__block">
			<p class="cc-multi-output__block__question">
				{{ block.question }}
			</p>
			<TextInput
				:id="'context_chat_multi_answer-' + i"
				class="cc-multi-output__block__answer"
				:value="block.answer"
				:is-output="true"
				:show-choose-button="false" />
			<div class="cc-multi-output__block__sources">
				<label for="v-select" class="cc-multi-output__block__sources__label">
					{{ outputShape.sources.description }}
				</label>
				<NcSelect
					:model-value="block.sources"
					:placeholder="t('assistant', 'No sources referenced')"
					:multiple="true"
					:close-on-select="false"
					:no-wrap="false"
					:label-outside="true"
					:append-to-body="false"
					:dropdown-should-open="() => false">
					<template #option="option">
						<a class="select-option" :href="option.url" target="_blank">
							<NcAvatar
								:size="24"
								:url="option.icon"
								:display-name="option.label" />
							<span class="multiselect-name">
								{{ option.label }}
							</span>
						</a>
					</template>
					<template #selected-option="option">
						<a class="select-option" :href="option.url" target="_blank">
							<NcAvatar
								:size="24"
								:url="option.icon"
								:display-name="option.label" />
							<span class="multiselect-name">
								{{ option.label }}
							</span>
						</a>
					</template>
				</NcSelect>
			</div>
		</div>
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import TextInput from '../fields/TextInput.vue'

export default {
	name: 'ContextChatMultiOutputForm',

	components: {
		NcAvatar,
		NcSelect,
		TextInput,
	},

	props: {
		outputShape: {
			type: Object,
			required: true,
		},
		output: {
			type: Object,
			required: true,
		},
	},

	computed: {
		// questions/answers/sources are three parallel lists (same order,
		// same length, as declared in the backend's output_shape): each
		// sources[i] entry is a JSON-encoded array of the sources used for
		// questions[i]/answers[i]. Zip them into one block per question.
		qaBlocks() {
			const questions = this.output?.questions ?? []
			const answers = this.output?.answers ?? []
			const rawSources = this.output?.sources ?? []
			return questions.map((question, i) => {
				let sources = []
				try {
					sources = rawSources[i] ? JSON.parse(rawSources[i]) : []
				} catch (e) {
					console.error('Failed to parse sources for question', i, e)
				}
				return {
					question,
					answer: answers[i] ?? '',
					sources,
				}
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.cc-multi-output {
	display: flex;
	flex-direction: column;
	gap: 24px;
	width: 100%;

	&__block {
		display: flex;
		flex-direction: column;
		align-items: start;
		gap: 8px;
		width: 100%;

		&__question {
			font-weight: bold;
			margin: 0;
		}

		&__answer {
			width: 100%;
		}

		&__sources {
			display: flex;
			flex-direction: column;

			:deep(.v-select) {
				min-width: 400px !important;

				> div {
					border: 2px solid var(--color-primary-element) !important;
				}

				.avatardiv {
					border-radius: 50%;

					&> img {
						border-radius: 0 !important;
					}
				}

				.vs__actions {
					display: none !important;
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
	}
}
</style>
