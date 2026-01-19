<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<component :is="getTableCell(column)"
		:column="column"
		:row-id="row.id"
		:value="getCellValue(column)"
		:element-id="elementId"
		:is-view="isView"
		:can-edit="canEdit" />
</template>

<script>
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
import TableCellRelation from './TableCellRelation.vue'
import TableCellTextRich from './TableCellEditor.vue'
import TableCellUsergroup from './TableCellUsergroup.vue'
import { ColumnTypes } from './../mixins/columnHandler.js'
import {
	TYPE_META_ID, TYPE_META_CREATED_BY, TYPE_META_CREATED_AT, TYPE_META_UPDATED_BY, TYPE_META_UPDATED_AT,
} from '../../../constants.ts'

export default {
	name: 'TableCell',
	components: {
		TableCellYesNo,
		TableCellStars,
		TableCellNumber,
		TableCellLink,
		TableCellProgress,
		TableCellHtml,
		TableCellDateTime,
		TableCellTextLine,
		TableCellSelection,
		TableCellMultiSelection,
		TableCellRelation,
		TableCellTextRich,
		TableCellUsergroup,
	},
	props: {
		column: {
			type: Object,
			default: () => {},
		},
		row: {
			type: Object,
			default: () => {},
		},
		elementId: {
			type: Number,
			default: null,
		},
		isView: {
			type: Boolean,
			default: true,
		},
		canEdit: {
			type: Boolean,
			default: false,
		},
	},
	methods: {
		getTableCell(column) {
			switch (column.type) {
			case ColumnTypes.TextLine: return 'TableCellTextLine'
			case ColumnTypes.TextLink: return 'TableCellLink'
			case ColumnTypes.TextRich:return 'TableCellTextRich'
			case ColumnTypes.Number: return 'TableCellNumber'
			case ColumnTypes.NumberStars: return 'TableCellStars'
			case ColumnTypes.NumberProgress: return 'TableCellProgress'
			case ColumnTypes.Selection: return 'TableCellSelection'
			case ColumnTypes.SelectionMulti: return 'TableCellMultiSelection'
			case ColumnTypes.SelectionCheck: return 'TableCellYesNo'
			case ColumnTypes.Relation: return 'TableCellRelation'
			case ColumnTypes.Datetime: return 'TableCellDateTime'
			case ColumnTypes.DatetimeDate: return 'TableCellDateTime'
			case ColumnTypes.DatetimeTime: return 'TableCellDateTime'
			case ColumnTypes.Usergroup: return 'TableCellUsergroup'
			default: return 'TableCellHtml'
			}
		},
		getCell(columnId) {
			if (columnId < 0) {
				// See metaColumns.js for mapping
				let value
				switch (columnId) {
				case TYPE_META_ID:
					value = this.row.id
					break
				case TYPE_META_CREATED_BY:
					value = this.row.createdBy
					break
				case TYPE_META_UPDATED_BY:
					value = this.row.lastEditBy
					break
				case TYPE_META_CREATED_AT:
					value = this.row.createdAt
					break
				case TYPE_META_UPDATED_AT:
					value = this.row.lastEditAt
					break
				}
				return { columnId, value }
			}
			return this.row.data.find(item => item.columnId === columnId) || null
		},
		getCellValue(column) {
			if (!this.row) {
				return null
			}

			// lets see if we have a value
			const cell = this.getCell(column.id)
			let value

			if (cell) {
				value = cell.value
			} else {
				// if no value is given, try to get the default value from the column definition
				value = column.default()
			}

			return column.parseValue(value)
		},
	},
}
</script>
