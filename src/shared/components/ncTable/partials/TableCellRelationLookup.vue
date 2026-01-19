<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<TableCell
		:column="targetColumn"
		:row="row"
		:value="targetValue"
		:element-id="elementId"
		:is-view="isView"
		:can-edit="false" />
</template>

<script>
import { useDataStore } from '../../../../store/data.js'
import TableCell from './TableCell.vue'

export default {
	name: 'TableCellRelationLookup',
	components: {
		TableCell,
	},
	props: {
		column: {
			type: Object,
			required: true,
		},
		row: {
			type: Object,
			required: true,
		},
		elementId: {
			type: Number,
			required: true,
		},
		isView: {
			type: Boolean,
			required: true,
		},
		value: {
			type: [String, Number, Array, Object, Boolean, null],
			default: null,
		},
	},
	computed: {
		targetColumn() {
			if (!this.column?.id) {
				return null
			}
			const dataStore = useDataStore()
			const relationData = dataStore.getRelations(this.column.id)
			return relationData?.column || null
		},
		targetValue() {
			if (!this.column?.customSettings?.targetColumnId || !this.value) {
				return null
			}

			const dataStore = useDataStore()
			const relationData = dataStore.getRelations(this.column.id)

			// Get the related row data for the current value (which is the relation ID)
			const relatedRow = relationData.data[this.value]
			if (!relatedRow) {
				return null
			}

			return relatedRow.label
		},
	},
}
</script>
