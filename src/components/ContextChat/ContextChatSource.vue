<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcRichText
		:text="text"
		:use-markdown="true"
		:reference-limit="1"
		:references="references"
		:autolink="true" />
</template>

<script>
import { NcRichText } from '@nextcloud/vue/dist/Components/NcRichText.js'

import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ContextChatSource',

	components: {
		NcRichText,
	},

	props: {
		source: {
			type: Object,
			required: true,
		},
	},

	data: () => {
		return {
			// we could initialize this with undefined but then NcRichText will try to find links
			// and resolve them before we had a chance to do so, resulting in unnecessary requests to references/resolve
			// with [] we inhibit the extract+resolve mechanism on NcRichText
			// TODO This can be removed (and all the custom extract+resolve logic) when fixed in NcRichText
			references: [],
		}
	},

	computed: {
		text() {
			return '[' + this.source.label + '](' + this.source.url + ')'
		},
	},

	mounted() {
		this.fetch()
	},

	methods: {
		fetch() {
			axios.get(generateOcsUrl('references/resolve') + `?reference=${encodeURIComponent(this.source.url)}`)
				.then((response) => {
					this.references = Object.values(response.data.ocs.data.references)
				})
				.catch((error) => {
					console.error('Failed to extract references', error)
				})
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing yet
</style>
