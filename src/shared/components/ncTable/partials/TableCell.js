/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ColumnTypes } from './../mixins/columnHandler.js'

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

const COMPONENT_BY_COLUMN_TYPE = {
	[ColumnTypes.TextLine]: TableCellTextLine,
	[ColumnTypes.TextLink]: TableCellLink,
	[ColumnTypes.TextRich]: TableCellTextRich,
	[ColumnTypes.Number]: TableCellNumber,
	[ColumnTypes.NumberStars]: TableCellStars,
	[ColumnTypes.NumberProgress]: TableCellProgress,
	[ColumnTypes.Selection]: TableCellSelection,
	[ColumnTypes.SelectionMulti]: TableCellMultiSelection,
	[ColumnTypes.SelectionCheck]: TableCellYesNo,
	[ColumnTypes.Datetime]: TableCellDateTime,
	[ColumnTypes.DatetimeDate]: TableCellDateTime,
	[ColumnTypes.DatetimeTime]: TableCellDateTime,
	[ColumnTypes.Usergroup]: TableCellUsergroup,
	[ColumnTypes.Relation]: TableCellRelation,
}

export function getTableCellComponent(column) {
	return COMPONENT_BY_COLUMN_TYPE[column?.type] || TableCellHtml
}
