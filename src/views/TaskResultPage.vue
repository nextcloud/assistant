<template>
	<NcContent app-name="textprocessing_assistant">
		<NcAppContent>
			PLOP
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'TaskResultPage',

	components: {
		NcContent,
		NcAppContent,
	},

	props: {
	},

	data() {
		return {
			generation: loadState('textprocessing_assistant', 'generation'),
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
