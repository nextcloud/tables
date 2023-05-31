import { AbstractDatetimeColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import Moment from '@nextcloud/moment'
import { Filters } from '../filter.js'

export default class DatetimeTimeColumn extends AbstractDatetimeColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.DatetimeTime
	}

	formatValue(value) {
		return Moment(value, 'HH:mm:ss').format('LT')
	}

	sort(mode) {
		const factor = mode === 'desc' ? -1 : 1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			const valueA = new Moment(tmpA, 'HH:mm')
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			const valueB = new Moment(tmpB, 'HH:mm')
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
		const time = new Moment(cell.value, 'YYYY-MM-DD HH:mm').format('lll')
		return super.isSearchStringFound(time, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const filterTime = new Moment(filterValue, 'HH:mm')
		const valueTime = new Moment(cell.value, 'HH:mm')

		const filterMethod = {
			[Filters.IsEqual.id]() { return filterTime.isSame(valueTime) },
			[Filters.IsGreaterThan.id]() { return filterTime.isBefore(valueTime) },
			[Filters.IsGreaterThanOrEqual.id]() { return filterTime.isSameOrBefore(valueTime) },
			[Filters.IsLowerThan.id]() { return filterTime.isAfter(valueTime) },
			[Filters.IsLowerThanOrEqual.id]() { return filterTime.isSameOrAfter(valueTime) },
		}[filter.operator]
		return super.isFilterFound(filterMethod, cell)
	}

}
