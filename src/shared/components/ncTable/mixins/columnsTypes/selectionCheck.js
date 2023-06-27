import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class SelectionCheckColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.SelectionCheck
	}

	sort(mode) {
		const factor = mode === 'desc' ? 1 : -1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			const valueA = (tmpA === true || tmpA === 'true')
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			const valueB = (tmpB === true || tmpB === 'true')
			return (valueA - valueB) * factor
		}
	}

	isSearchStringFound(cell, searchString) {
		return false
	}

	isFilterFound(cell, filter) {
		const filterValue = '' + filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[FilterIds.IsEqual]() { return (cell.value === 'true' && filterValue === 'yes') || (cell.value === 'false' && filterValue === 'no') },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
