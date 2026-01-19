<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="column.mandatory" :description="column.description" :readonly="column.readonly">
		<TableCellRelationLookup :column="column" :value="value" />
	</RowFormWrapper>
</template>

<script>
import RowFormWrapper from './RowFormWrapper.vue'
import TableCellRelationLookup from '../TableCellRelationLookup.vue'

export default {
	name: 'RelationLookupForm',
	components: {
		RowFormWrapper,
		TableCellRelationLookup,
	},
	props: {
		column: {
			type: Object,
			required: true,
		},
		value: {
			type: [String, Number, Array, Object, Boolean, null],
			default: null,
		},
	},
}
</script>
