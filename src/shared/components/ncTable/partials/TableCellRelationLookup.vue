<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<component
			:is="targetCellComponent"
			v-if="targetColumn && targetCellComponent"
			:column="targetColumn"
			:row-id="rowId"
			:value="targetValue"
			:is-relation-supplement="true" />
	</div>
</template>

<script>
import { useDataStore } from '../../../../store/data.js'
import { ColumnTypes } from './../mixins/columnHandler.js'

// fixme: try to reuse already existent infrastructure for it
// Import all cell components that could be used for target columns
import TableCellHtml from './TableCellHtml.vue'
import TableCellProgress from './TableCellProgress.vue'
import TableCellLink from './TableCellLink.vue'
import TableCellNumber from './TableCellNumber.vue'
import TableCellStars from './TableCellStars.vue'
import TableCellYesNo from './TableCellYesNo.vue'
import TableCellDateTime from './TableCellDateTime.vue'
import TableCellTextLine from './TableCellTextLine.vue'
import TableCellSelection from './TableCellSelection.vue'
import TableCellMultiSelection from './TableCellMultiSelection.vue'
import TableCellTextRich from './TableCellEditor.vue'
import TableCellUsergroup from './TableCellUsergroup.vue'
import TableCellRelation from './TableCellRelation.vue'

export default {
	name: 'TableCellRelationLookup',
	components: {
		TableCellHtml,
		TableCellProgress,
		TableCellLink,
		TableCellNumber,
		TableCellStars,
		TableCellYesNo,
		TableCellDateTime,
		TableCellTextLine,
		TableCellSelection,
		TableCellMultiSelection,
		TableCellTextRich,
		TableCellUsergroup,
		TableCellRelation,
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
		targetCellComponent() {
			if (!this.targetColumn) {
				return null
			}
			switch (this.targetColumn.type) {
			case ColumnTypes.TextLine: return 'TableCellTextLine'
			case ColumnTypes.TextLink: return 'TableCellLink'
			case ColumnTypes.TextRich: return 'TableCellTextRich'
			case ColumnTypes.Number: return 'TableCellNumber'
			case ColumnTypes.NumberStars: return 'TableCellStars'
			case ColumnTypes.NumberProgress: return 'TableCellProgress'
			case ColumnTypes.Selection: return 'TableCellSelection'
			case ColumnTypes.SelectionMulti: return 'TableCellMultiSelection'
			case ColumnTypes.SelectionCheck: return 'TableCellYesNo'
			case ColumnTypes.Datetime: return 'TableCellDateTime'
			case ColumnTypes.DatetimeDate: return 'TableCellDateTime'
			case ColumnTypes.DatetimeTime: return 'TableCellDateTime'
			case ColumnTypes.Usergroup: return 'TableCellUsergroup'
			case ColumnTypes.Relation: return 'TableCellRelation'
			default: return 'TableCellHtml'
			}
		},
		targetValue() {
			if (!this.column?.customSettings?.targetColumnId) {
				return null
			}

			const dataStore = useDataStore()
			const relationData = dataStore.getRelations(this.column.id)

			let value = this.value
			if (typeof this.value === 'object' && this.value !== null) {
				value = this.value.value
			}

			if (!relationData?.data || !value) {
				return null
			}

			// Get the related row data for the current value (which is the relation ID)
			const relatedRow = relationData.data[value]
			if (!relatedRow) {
				return null
			}

			return relatedRow.label
		},
	},
}
</script>
