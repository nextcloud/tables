/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ColumnTypes } from './columnHandler.js'
import DatetimeDateColumn from './columnsTypes/datetimeDate.js'
import DatetimeColumn from './columnsTypes/datetime.js'
import DatetimeTimeColumn from './columnsTypes/datetimeTime.js'
import NumberColumn from './columnsTypes/number.js'
import NumberProgressColumn from './columnsTypes/numberProgress.js'
import NumberStarsColumn from './columnsTypes/numberStars.js'
import SelectionCheckColumn from './columnsTypes/selectionCheck.js'
import SelectionColumn from './columnsTypes/selection.js'
import SelectionMutliColumn from './columnsTypes/selectionMulti.js'
import TextLineColumn from './columnsTypes/textLine.js'
import TextLinkColumn from './columnsTypes/textLink.js'
import TextLongColumn from './columnsTypes/textLong.js'
import TextRichColumn from './columnsTypes/textRich.js'
import UsergroupColumn from './columnsTypes/usergroup.js'

export function parseCol(col) {
	const columnType = col.type + (col.subtype === '' ? '' : '-' + col.subtype)
	switch (columnType) {
	case ColumnTypes.TextLine: return new TextLineColumn(col)
	case ColumnTypes.TextLink: return new TextLinkColumn(col)
	case ColumnTypes.TextLong: return new TextLongColumn(col)
	case ColumnTypes.TextRich: return new TextRichColumn(col)
	case ColumnTypes.Number: return new NumberColumn(col)
	case ColumnTypes.NumberStars: return new NumberStarsColumn(col)
	case ColumnTypes.NumberProgress: return new NumberProgressColumn(col)
	case ColumnTypes.Selection: return new SelectionColumn(col)
	case ColumnTypes.SelectionMulti: return new SelectionMutliColumn(col)
	case ColumnTypes.SelectionCheck: return new SelectionCheckColumn(col)
	case ColumnTypes.Datetime: return new DatetimeColumn(col)
	case ColumnTypes.DatetimeDate: return new DatetimeDateColumn(col)
	case ColumnTypes.DatetimeTime: return new DatetimeTimeColumn(col)
	case ColumnTypes.Usergroup: return new UsergroupColumn(col)
	default: throw Error(columnType + ' is not a valid column type!')
	}
}
