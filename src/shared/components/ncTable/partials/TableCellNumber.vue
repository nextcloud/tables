<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		{{ column.numberPrefix }}{{ getValue }}{{ column.numberSuffix }}
	</div>
</template>

<script>

export default {
	name: 'TableCellNumber',
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
			type: Number,
			default: null,
		},
	},
	computed: {
		getValue() {
			if (this.value === null) {
				return null
			}
			return this.value.toFixed(this.column?.numberDecimals)
		},
	},
}
</script>

<style lang="scss" scoped>

div {
	text-align: right;
}

</style>
