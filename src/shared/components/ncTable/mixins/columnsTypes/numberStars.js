import { AbstractNumberColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { Filters } from '../filter.js'

export default class NumberStarsColumn extends AbstractNumberColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.NumberStars
	}

	sort(mode) {
		const factor = mode === 'desc' ? -1 : 1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value
			const valueA = parseInt(tmpA)
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value
			const valueB = parseInt(tmpB)
			return ((valueA < valueB) ? 1 : (valueA > valueB) ? -1 : 0) * factor
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(('' + cell.value), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[Filters.IsEqual.id]() { return parseInt(cell.value) === parseInt(filterValue) },
			[Filters.IsGreaterThan.id]() { return parseInt(cell.value) > parseInt(filterValue) },
			[Filters.IsGreaterThanOrEqual.id]() { return parseInt(cell.value) >= parseInt(filterValue) },
			[Filters.IsLowerThan.id]() { return parseInt(cell.value) < parseInt(filterValue) },
			[Filters.IsLowerThanOrEqual.id]() { return parseInt(cell.value) <= parseInt(filterValue) },
		}[filter.operator]
		return super.isFilterFound(filterMethod, cell)
	}

}
