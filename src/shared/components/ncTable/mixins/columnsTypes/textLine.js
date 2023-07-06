import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class TextLineColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextLine
		this.textMaxLength = data.textMaxLength
	}

	sort(mode) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			const valueA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			const valueB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
		}
	}

	getValueString(valueObject) {
		return valueObject.value.replace(/(<([^>]+)>)/ig, '')
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(cell.value, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[FilterIds.Contains]() { return cell.value.includes(filterValue) },
			[FilterIds.BeginsWith]() { return cell.value.startsWith(filterValue) },
			[FilterIds.EndsWith]() { return cell.value.endsWith(filterValue) },
			[FilterIds.IsEqual]() { return cell.value === filterValue },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]

		return super.isFilterFound(filterMethod, cell)

	}

}
