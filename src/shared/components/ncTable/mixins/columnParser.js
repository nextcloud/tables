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

export function parseCol(col) {
	const columnType = col.type + (col.subtype === '' ? '' : '-' + col.subtype)
	switch (columnType) {
	case 'text-line': return new TextLineColumn(col)
	case 'text-link': return new TextLinkColumn(col)
	case 'text-long': return new TextLongColumn(col)
	case 'text-rich': return new TextRichColumn(col)
	case 'number': return new NumberColumn(col)
	case 'number-stars': return new NumberStarsColumn(col)
	case 'number-progress': return new NumberProgressColumn(col)
	case 'selection': return new SelectionColumn(col)
	case 'selection-multi': return new SelectionMutliColumn(col)
	case 'selection-check': return new SelectionCheckColumn(col)
	case 'datetime': return new DatetimeColumn(col)
	case 'datetime-date': return new DatetimeDateColumn(col)
	case 'datetime-time': return new DatetimeTimeColumn(col)
	default: throw Error(col.type + '-' + col.subtype + ' is not a valid column type!')
	}
}
