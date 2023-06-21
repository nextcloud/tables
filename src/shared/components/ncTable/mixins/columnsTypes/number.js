import { AbstractNumberColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class NumberColumn extends AbstractNumberColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Number
		this.numberDecimals = data.numberDecimals
		this.numberMax = data.numberMax
		this.numberMin = data.numberMin
		this.numberPrefix = data.numberPrefix
		this.numberSuffix = data.numberSuffix
	}

	sort(mode) {
		const factor = mode === 'desc' ? -1 : 1
		return (rowA, rowB) => {
			const valueA = rowA.data.find(item => item.columnId === this.id)?.value || null
			const valueB = rowB.data.find(item => item.columnId === this.id)?.value || null
			if (!valueA && valueB) {
				return -1 * factor
			}
			if (valueA && !valueB) {
				return 1 * factor
			}
			if (!valueA && !valueB) {
				return 0
			}
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(('' + cell.value), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[FilterIds.IsEqual]() { return parseInt(cell.value) === parseInt(filterValue) },
			[FilterIds.IsGreaterThan]() { return parseInt(cell.value) > parseInt(filterValue) },
			[FilterIds.IsGreaterThanOrEqual]() { return parseInt(cell.value) >= parseInt(filterValue) },
			[FilterIds.IsLowerThan]() { return parseInt(cell.value) < parseInt(filterValue) },
			[FilterIds.IsLowerThanOrEqual]() { return parseInt(cell.value) <= parseInt(filterValue) },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
