import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { Filters } from '../filter.js'

export default class TextLinkColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextLink
	}

	sort(mode) {
		const factor = mode === 'desc' ? -1 : 1
		return (rowA, rowB) => {
			const valueA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			const valueB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(cell.value, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[Filters.Contains.id]() { return cell.value.includes(filterValue) },
			[Filters.BeginsWith.id]() { return cell.value.startsWith(filterValue) },
			[Filters.EndsWith.id]() { return cell.value.endsWith(filterValue) },
			[Filters.IsEqual.id]() { return cell.value === filterValue },
		}[filter.operator]
		return super.isFilterFound(filterMethod, cell)
	}

}
