<template>
	<div class="cc-output">
		<div class="cc-output__text">
			<TextField
				field-key="cc-output-text"
				:value="output.output"
				:field="outputShape.output"
				:is-output="true" />
		</div>
		<div class="cc-output__sources">
			<label for="v-select" class="cc-output__sources__label">
				{{ outputShape.sources.description }}
			</label>
			<NcSelect
				:value="sources"
				:placeholder="t('assistant', 'No sources referenced')"
				:multiple="true"
				:close-on-select="false"
				:no-wrap="false"
				:label-outside="true"
				:append-to-body="false"
				:dropdown-should-open="() => false">
				<template #option="option">
					<a class="select-option" :href="option.url">
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
					<a class="select-option" :href="option.url">
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
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import TextField from '../fields/TextField.vue'

export default {
	name: 'ContextChatOutputForm',

	components: {
		NcAvatar,
		NcSelect,
		TextField,
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
		sources() {
			try {
				return JSON.parse(this.output.sources)
			} catch (e) {
				console.error('Failed to parse sources', e)
				return []
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cc-output {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 8px;

	.advanced {
		width: 100%;
	}

	&__text {
		width: 100%;
	}

	&__sources {
		display: flex;
		flex-direction: column;

		:deep .v-select {
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
</style>
