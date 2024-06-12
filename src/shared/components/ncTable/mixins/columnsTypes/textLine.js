import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'
import { TYPE_META_CREATED_BY, TYPE_META_UPDATED_BY } from '../../../../constants.js'

export default class TextLineColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextLine
		this.textMaxLength = data.textMaxLength
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			let valueA = rowA.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			let valueB = rowB.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			if (this.id === TYPE_META_CREATED_BY) {
				valueA = rowA.createdBy?.toLowerCase() || ''
				valueB = rowB.createdBy?.toLowerCase() || ''
			}
			if (this.id === TYPE_META_UPDATED_BY) {
				valueA = rowA.lastEditBy?.toLowerCase() || ''
				valueB = rowB.lastEditBy?.toLowerCase() || ''
			}
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	getValueString(valueObject) {
		return valueObject.value.replace(/(<([^>]+)>)/ig, '')
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(cell.value, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = (filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value).toLowerCase()
		const cellValue = cell.value?.toLowerCase()
		if (!cellValue & filter.operator.id !== FilterIds.IsEmpty) return false
		const filterMethod = {
			[FilterIds.Contains]() { return cellValue.includes(filterValue) },
			[FilterIds.BeginsWith]() { return cellValue.startsWith(filterValue) },
			[FilterIds.EndsWith]() { return cellValue.endsWith(filterValue) },
			[FilterIds.IsEqual]() { return cellValue === filterValue },
			[FilterIds.IsEmpty]() { return !cellValue },
		}[filter.operator.id]

		return super.isFilterFound(filterMethod, cell)

	}

}
