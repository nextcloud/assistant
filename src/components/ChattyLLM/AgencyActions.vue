<template>
	<div class="agency-actions">
		<AgencyAction v-for="action in actions"
			:key="action.id"
			:action="action" />
	</div>
</template>

<script>
import AgencyAction from './AgencyAction.vue'

export default {
	name: 'AgencyActions',

	components: {
		AgencyAction,
	},

	props: {
		actions: {
			type: Array,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.agency-actions {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 4px;
}
</style>
