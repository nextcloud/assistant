<template>
	<div class="tti-input-form">
		<div class="line">
			<NcCheckboxRadioSwitch class="include-query"
				:checked="displayPrompt"
				@update:checked="onDisplayPromptChanged">
				{{ t('assistant', 'Include the prompt in the result') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div class="spacer" />
		<div class="line">
			<label for="nResults">{{ t('assistant', 'Number of results') }}</label>
			<input id="nResults"
				:value="nResults"
				type="number"
				min="1"
				max="10"
				@input="onInputChanged">
		</div>
	</div>
</template>

<script>
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'Text2ImageInputForm',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		displayPrompt: {
			type: Boolean,
			required: true,
		},
		nResults: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	mounted() {
	},

	methods: {
		onInputChanged(e) {
			this.$emit('update:nResults', parseInt(e.target.value))
		},
		onDisplayPromptChanged(e) {
			this.$emit('update:displayPrompt', e)
		},
	},
}
</script>

<style scoped lang="scss">
.tti-input-form {
	width: 100%;
	padding: 12px 0;
	.line {
		display: flex;
		align-items: center;
		margin-top: 8px;

		input,
		select {
			margin-left: 24px;
			width: 200px;
		}
	}

	input[type=number] {
		width: 80px;
		appearance: initial !important;
		-moz-appearance: initial !important;
		-webkit-appearance: initial !important;
	}
}
</style>
