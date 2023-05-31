import { AbstractDatetimeColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import Moment from '@nextcloud/moment'
import { Filters } from '../filter.js'

export default class DatetimeColumn extends AbstractDatetimeColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Datetime
	}

	formatValue(value) {
		return Moment(value, 'YYYY-MM-DD HH:mm:ss').format('lll')
	}

	sort(mode) {
		const factor = mode === 'desc' ? -1 : 1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			const valueA = new Moment(tmpA, 'YYY-MM-DD HH:mm')
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			const valueB = new Moment(tmpB, 'YYY-MM-DD HH:mm')
			if (!tmpA && tmpB) {
				return -1 * factor
			}
			if (tmpA && !tmpB) {
				return 1 * factor
			}
			if (!tmpA && !tmpB) {
				return 0
			}
			return (valueA.diff(valueB)) * factor
		}
	}

	isSearchStringFound(cell, searchString) {
		const date = new Moment(cell.value, 'YYYY-MM-DD HH:mm').format('lll')
		return super.isSearchStringFound(date, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const filterDate = new Moment(filterValue, 'YYY-MM-DD HH:mm')
		const valueDate = new Moment(cell.value, 'YYY-MM-DD HH:mm')

		const filterMethod = {
			[Filters.IsEqual.id]() { return filterDate.isSame(valueDate) },
			[Filters.IsGreaterThan.id]() { return filterDate.isBefore(valueDate) },
			[Filters.IsGreaterThanOrEqual.id]() { return filterDate.isSameOrBefore(valueDate) },
			[Filters.IsLowerThan.id]() { return filterDate.isAfter(valueDate) },
			[Filters.IsLowerThanOrEqual.id]() { return filterDate.isSameOrAfter(valueDate) },
		}[filter.operator]
		return super.isFilterFound(filterMethod, cell)
	}

}
