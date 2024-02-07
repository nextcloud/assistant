<!-- SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com> -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

<template>
	<div class="display-container">
		<NcRichContenteditable v-if="result !== null && result !== ''"
			id="free-prompt-output"
			ref="output"
			:value.sync="result"
			class="editable-preview"
			:multiline="true"
			:disabled="loading"
			:placeholder="t('assistant', 'Text generation content')"
			:link-autocomplete="false"
			@update:value="onTextEdit" />
		<div v-if="loading">
			<NcLoadingIcon :size="64" />
			<div v-if="processing" class="loading-info">
				<div v-if="bgProcessingScheduled" class="task-scheduled-info">
					{{ t('assistant', 'The text generation task was scheduled to run in the background.') }}
					<div v-if="timeUntilCompletion !== null">
						{{ t('assistant', 'Estimated completion time: ') + timeUntilCompletion }}
					</div>
					<div v-else>
						{{ t('assistant', 'This can take a while…') }}
					</div>
				</div>
				<div v-else>
					{{ t('assistant', 'Some generations are still being processed in the background! Showing finished generations.') }}
				</div>
			</div>
			<div v-else>
				{{ t('assistant', 'Loading generations…') }}
			</div>
		</div>
		<div v-if="!loading" class="button-wrapper">
			<NcButton :disabled="result === null || loading"
				type="secondary"
				:title="t('assistant', 'Copy output text to clipboard')"
				@click="onCopy">
				{{ t('assistant', 'Copy output') }}
				<template #icon>
					<ClipboardCheckOutlineIcon v-if="copied" />
					<ContentCopyIcon v-else />
				</template>
			</NcButton>
			<NcButton :disabled="result === originalResult || loading"
				type="secondary"
				:title="t('assistant', 'Reset the output value to the originally generated one')"
				@click="delayedReset">
				{{ t('assistant', 'Reset') }}
			</NcButton>
			<NcCheckboxRadioSwitch
				:checked.sync="includePrompt"
				:disabled="loading || result === ''"
				@update:checked="onIncludePromptToggle">
				{{ t('assistant', 'Include prompt in the final result') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'

import VueClipboard from 'vue-clipboard2'
import Vue from 'vue'

Vue.use(VueClipboard)

export default {
	name: 'FreePromptGenerationDisplay',

	components: {
		NcLoadingIcon,
		NcRichContenteditable,
		NcButton,
		NcCheckboxRadioSwitch,
		ContentCopyIcon,
		ClipboardCheckOutlineIcon,
	},

	props: {
		genId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			originalResponse: null,
			originalResult: null,
			result: null,
			copied: false,
			loading: true,
			processing: false,
			bgProcessingScheduled: false,
			includePrompt: false,
			prompt: '',
			timeUntilCompletion: null,
			rawCompletionTimestamp: null,
			closed: false,
		}
	},

	mounted() {
		this.getResults()
	},

	beforeDestroy() {
		this.closed = true
	},

	methods: {
		getResults() {
			// Check if this element has already been closed/destroyed
			if (this.closed) {
				return
			}
			const config = {
				params: {
					genId: this.genId,
				},
			}
			const url = generateUrl('/apps/assistant/f/get_outputs')
			return axios.get(url, config)
				.then((response) => {
					const data = response.data
					if (data.length && data.length > 0) {
						if (!data.length || data[0]?.status === undefined) {
							this.loading = false
							showError(t('assistant', 'Unexpected server response'))
							this.$emit('error')
							return
						}

						if (this.rawCompletionTimestamp === null) {
							// Get the largest timestamp of all generations
							this.rawCompletionTimestamp = Math.max(...data.map(c => c.completion_time))
							this.updateTimeUntilCompletion(this.rawCompletionTimestamp)
						}

						// Check if processing of all completions is finished
						// 1 = scheduled, 2 = running
						const numGensProcessing = data.filter(c => c.status === 1 || c.status === 2).length
						if (numGensProcessing === 0) {
							// 4 = failed, 0 = unknown
							const nFailures = data.filter(c => c.status === 4 || c.status === 0).length
							if (nFailures > 0) {
								if (nFailures === data.length) {
									showError(t('assistant', 'The processing of generations failed.'))
									this.loading = false
									this.result = null
									this.$emit('error')
									return
								}
								showError(t('assistant', 'The processing of some generations failed.'))
							}
							this.loading = false
							this.$emit('loaded')
							this.processCompletion(data)
						} else {
							if (numGensProcessing === data.length) {
								this.bgProcessingScheduled = true
							}
							this.processing = true
							this.$emit('scheduled')
							this.processCompletion(data)
							setTimeout(() => {
								this.getResults()
							}, 5000)
						}
					} else {
						this.loading = false
						this.$emit('error')
						this.error = response.data.error
					}
				})
				.catch((error) => {
					this.loading = false
					this.$emit('error')
					console.error('Text  completions request error', error)
					showError(
						t('assistant', 'Text generation error') + ': '
						+ (error.response?.data?.body?.error?.message
							|| error.response?.data?.body?.error?.code
							|| error.response?.data?.error
							|| t('assistant', 'Unknown text generation API error')
						),
					)
				})
		},

		onReset() {
			this.result = this.originalResult
		},

		delayedReset() {
			// This is a hack to sure the text box is updated
			// when we reset the text since removing newlines or spaces
			// from the end of the text does not trigger an update.

			// Delete any trailing newlines
			this.result = this.result.replace(/\n+$/, '')
			this.result += '.'

			// Let the ui refresh before resetting the text
			setTimeout(() => {
				this.onReset()
			}, 0)
		},

		async onCopy() {
			try {
				const container = this.$refs.output.$el
				await this.$copyText(this.result.trim(), container)
				this.copied = true
				setTimeout(() => {
					this.copied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('assistant', 'Result could not be copied to clipboard'))
			}
		},

		processCompletion(response) {
			this.originalResponse = response
			const totalGens = response.length
			// Drop completions that are not yet finished
			this.prompt = response[0].prompt
			response = response.filter(c => c.status === 3)
			const answers = response.filter(c => !!c.text).map(c => c.text.replace(/^\s+|\s+$/g, ''))
			if (answers.length > 0) {
				if (totalGens === 1) {
					this.originalResult = this.result = this.includePrompt
						? t('assistant', 'Prompt') + '\n' + this.prompt + '\n\n' + t('assistant', 'Result') + '\n' + answers[0]
						: answers[0]
				} else {
					const multiAnswers = answers.map((a, i) => {
						return t('assistant', 'Result {index}', { index: i + 1 }) + '\n' + a
					})
					this.originalResult = this.result = this.includePrompt
						? t('assistant', 'Prompt') + '\n' + this.prompt + '\n\n' + multiAnswers.join('\n\n')
						: multiAnswers.join('\n\n')
				}
			}
			this.$emit('update:result', this.result)
		},

		onIncludePromptToggle() {
			this.processCompletion(this.originalResponse)
		},

		onTextEdit() {
			this.$emit('update:result', this.result)
		},

		updateTimeUntilCompletion(completionTimeStamp) {
			const timeDifference = completionTimeStamp - moment().unix()
			if (timeDifference < 60) {
				this.timeUntilCompletion = null
				return
			}

			this.timeUntilCompletion = moment.unix(completionTimeStamp).fromNow()

			// Schedule next update:
			if (!this.closed) {
				setTimeout(() => {
					this.updateTimeUntilCompletion(completionTimeStamp)
				}, 30000)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.display-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100%;
	width: 100%;
	margin-top: 24px;

	.loading-info {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		margin-top: 12px;
		margin-bottom: 24px;

		.task-scheduled-info {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
		}
	}

	.button-wrapper {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: center;
		margin-top: 12px;
		margin-bottom: 12px;

		>* {
			margin-right: 12px;
			margin-left: 12px;
		}
	}

	.editable-preview {
		display: flex;
		flex-direction: column;
		width: 100%;
		overflow-y: auto;
		overflow-x: hidden;
		padding: 12px;
		line-height: 1.5;
		white-space: pre-wrap;
		word-break: break-word;
		margin-bottom: 24px;
	}
}
</style>
