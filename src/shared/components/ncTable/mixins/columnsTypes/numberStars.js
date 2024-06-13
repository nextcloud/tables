import { AbstractNumberColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class NumberStarsColumn extends AbstractNumberColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.NumberStars
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? 1 : -1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value
			return tmpA.localeCompare(tmpB, undefined, { numeric: true }) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(('' + cell.value), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[FilterIds.IsEqual]() { return parseInt(cell.value ? cell.value : 0) === parseInt(filterValue) },
			[FilterIds.IsGreaterThan]() { return parseInt(cell.value ? cell.value : 0) > parseInt(filterValue) },
			[FilterIds.IsGreaterThanOrEqual]() { return parseInt(cell.value ? cell.value : 0) >= parseInt(filterValue) },
			[FilterIds.IsLowerThan]() { return parseInt(cell.value ? cell.value : 0) < parseInt(filterValue) },
			[FilterIds.IsLowerThanOrEqual]() { return parseInt(cell.value ? cell.value : 0) <= parseInt(filterValue) },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
