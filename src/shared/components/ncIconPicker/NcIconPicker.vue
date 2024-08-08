<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcPopover popup-role="dialog" :shown.sync="showIconPicker">
		<template #trigger="slotProps">
			<slot v-bind="slotProps" />
		</template>

		<IconPicker :icons="icons" @select="select" />
	</NcPopover>
</template>

<script>
import iconData from '../../../../img/material/meta.json'

import { NcPopover } from '@nextcloud/vue'
import IconPicker from './partials/IconPicker.vue'

export default {
	name: 'NcIconPicker',

	components: {
		NcPopover,
		IconPicker,
	},

	props: {
		closeOnSelect: {
			type: Boolean,
			default: true,
		},
	},

	emits: ['select'],

	data() {
		return {
			icons: iconData,
			showIconPicker: false,
		}
	},

	methods: {
		select(icon) {
			this.$emit('select', icon)

			if (this.closeOnSelect) {
				this.showIconPicker = false
			}
		},
	},
}
</script>
