<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<component :is="getTableCellComponent(targetColumn)"
			v-if="targetColumn"
			:key="relatedRow?.id"
			:column="targetColumn"
			:row-id="rowId"
			:value="targetValue"
			:can-edit="false" />
	</div>
</template>

<script>
import { useDataStore } from '../../../../store/data.js'
import { getTableCellComponent } from './TableCell.js'

export default {
	name: 'TableCellRelationLookup',
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
			required: true,
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
		relationValues() {
			const dataStore = useDataStore()
			const relationData = dataStore.getRelations(this.column.id)
			return relationData?.values || null
		},
		relatedRow() {
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
			return this.relationValues[value] ?? null
		},
		targetValue() {
			if (!this.relatedRow || !this.targetColumn) {
				return null
			}

			return this.targetColumn.parseValue(this.relatedRow.value)
		},
	},
	methods: {
		getTableCellComponent,
	},
}
</script>
