<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!-- eslint-disable-next-line vue/no-v-html -->
	<div v-if="value">
		{{ value | truncate(50) }}
	</div>
</template>

<script>
export default {
	name: 'TableCellTextLine',

	filters: {
		truncate(string, num) {
			if (string?.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

	props: {
		column: {
			type: Object,
			default: () => {},
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: String,
			default: '',
		},
	},
}
</script>
