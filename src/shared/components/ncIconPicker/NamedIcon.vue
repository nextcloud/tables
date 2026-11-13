<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcIconSvgWrapper v-if="svg" :svg="svg" :size="size" />
	<slot v-else />
</template>

<script>
import { NcIconSvgWrapper } from '@nextcloud/vue'
import svgHelper from './mixins/svgHelper.js'

/**
 * A named icon from the set the icon picker offers, loaded by name. Without a name it
 * renders its default slot.
 */
export default {
	name: 'NamedIcon',
	components: {
		NcIconSvgWrapper,
	},
	mixins: [svgHelper],
	props: {
		name: {
			type: String,
			default: null,
		},
		size: {
			type: Number,
			default: 20,
		},
	},
	data() {
		return {
			svg: null,
		}
	},
	watch: {
		name: {
			immediate: true,
			async handler(name) {
				// Counts the loads, so an icon that a newer name overtook does not replace it
				this.iconRequest = (this.iconRequest ?? 0) + 1
				const request = this.iconRequest
				const svg = name ? await this.getContextIcon(name) : null
				if (request === this.iconRequest) {
					this.svg = svg
				}
			},
		},
	},
}
</script>
