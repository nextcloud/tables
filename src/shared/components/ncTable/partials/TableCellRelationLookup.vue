<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<TableCell
			v-if="targetColumn"
			:column="targetColumn"
			:row-id="rowId"
			:value="targetValue"
			:can-edit="false" />
	</div>
</template>

<script>
import { useDataStore } from '../../../../store/data.js'

export default {
	name: 'TableCellRelationLookup',
	components: {
		// Loaded asynchronously to break the circular dependency with TableCell,
		// which itself resolves TableCellRelationLookup for relation lookup columns.
		TableCell: () => import('./TableCell.vue'),
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
			type: [String, Number, Array, Object, Boolean, null],
			default: null,
		},
	},
	data() {
		return {
			loading: false,
		}
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
		relationValues() {
			const dataStore = useDataStore()
			const relationData = dataStore.getRelations(this.column.id)
			return relationData?.values || null
		},
		targetValue() {
			if (!this.column?.customSettings?.targetColumnId) {
				return null
			}

			let value = this.value
			if (typeof this.value === 'object' && this.value !== null) {
				value = this.value.value
			}

			if (!this.relationValues || !value) {
				return null
			}

			// Get the related row data for the current value (which is the relation ID)
			const relatedRow = this.relationValues[value]
			if (!relatedRow) {
				return null
			}

			return relatedRow.value
		},
	},
}
</script>
