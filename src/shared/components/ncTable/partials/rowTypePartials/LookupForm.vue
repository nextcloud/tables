<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="false" :description="column.description" :readonly="true">
		<TableCellLookup :column="column" :value="value" />
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'
import TableCellLookup from '../TableCellLookup.vue'

export default {
	name: 'LookupForm',
	components: {
		RowFormWrapper,
		TableCellLookup,
	},
	props: {
		column: {
			type: Object,
			required: true,
		},
		value: {
			type: [String, Number, Object],
			default: null,
		},
	},
}
</script>
