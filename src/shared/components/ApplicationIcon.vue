<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcIconSvgWrapper v-if="svg" :svg="svg" :size="size" />
	<ViewGridOutline v-else :size="size" />
</template>

<script>
import { NcIconSvgWrapper } from '@nextcloud/vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'
import svgHelper from './ncIconPicker/mixins/svgHelper.js'

/**
 * The material icon of an application, loaded by name.
 */
export default {
	name: 'ApplicationIcon',
	components: {
		NcIconSvgWrapper,
		ViewGridOutline,
	},
	mixins: [svgHelper],
	props: {
		iconName: {
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
		iconName: {
			immediate: true,
			async handler(iconName) {
				this.svg = iconName ? await this.getContextIcon(iconName) : null
			},
		},
	},
}
</script>
