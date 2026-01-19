<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<component :is="getTableCell(column)"
		:column="column"
		:row="row"
		:value="getCellValue(column)"
		:element-id="elementId"
		:is-view="isView"
		:can-edit="canEdit" />
</template>

<script>
import TableCellDateTime from './TableCellDateTime.vue'
import TableCellEditor from './TableCellEditor.vue'
import TableCellHtml from './TableCellHtml.vue'
import TableCellLink from './TableCellLink.vue'
import TableCellMultiSelection from './TableCellMultiSelection.vue'
import TableCellNumber from './TableCellNumber.vue'
import TableCellProgress from './TableCellProgress.vue'
import TableCellRelation from './TableCellRelation.vue'
import TableCellRelationLookup from './TableCellRelationLookup.vue'
import TableCellSelection from './TableCellSelection.vue'
import TableCellStars from './TableCellStars.vue'
import TableCellTextLine from './TableCellTextLine.vue'
import TableCellUsergroup from './TableCellUsergroup.vue'
import TableCellYesNo from './TableCellYesNo.vue'
import { ColumnTypes } from '../mixins/columnHandler.js'

export default {
	name: 'TableCell',

	props: {
		row: {
			type: Object,
			required: true,
		},
		column: {
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
		canEdit: {
			type: Boolean,
			required: true,
		},
	},

	methods: {
		getTableCell(column) {
			switch (column.type) {
			case ColumnTypes.Datetime: return TableCellDateTime
			case ColumnTypes.DatetimeDate: return TableCellDateTime
			case ColumnTypes.DatetimeTime: return TableCellDateTime
			case ColumnTypes.Number: return TableCellNumber
			case ColumnTypes.NumberProgress: return TableCellProgress
			case ColumnTypes.NumberStars: return TableCellStars
			case ColumnTypes.Relation: return TableCellRelation
			case ColumnTypes.RelationLookup: return TableCellRelationLookup
			case ColumnTypes.Selection: return TableCellSelection
			case ColumnTypes.SelectionCheck: return TableCellYesNo
			case ColumnTypes.SelectionMulti: return TableCellMultiSelection
			case ColumnTypes.TextLine: return TableCellTextLine
			case ColumnTypes.TextLink: return TableCellLink
			case ColumnTypes.TextRich:return TableCellEditor
			case ColumnTypes.Usergroup: return TableCellUsergroup
			default: return TableCellHtml
			}
		},

		getCellValue(column) {
			if (!this.rowId) {
				return null
			}

			const cell = this.getCell(column.id)
			const value = cell ? cell.value : column.default()

			return column.parseValue(value)
		},
	},
}
</script>
