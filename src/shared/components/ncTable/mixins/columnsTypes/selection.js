import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class SelectionColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Selection
		this.selectionOptions = data.selectionOptions
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null
		return this.getLabel(valueObject.value)
	}

	getLabel(id) {
		const i = this.selectionOptions?.findIndex((obj) => obj.id === id)
		return this.selectionOptions[i]?.label
	}

	isDeletedLabel(value) {
		const i = this.selectionOptions?.findIndex((obj) => obj.id === value)
		return !!this.selectionOptions[i]?.deleted
	}

	sort(mode) {
		const factor = mode === 'desc' ? -1 : 1
		return (rowA, rowB) => {
			const selectionIdA = rowA.data.find(item => item.columnId === this.id)?.value || null
			const valueA = selectionIdA !== null ? this.selectionOptions.find(item => item.id === selectionIdA)?.label : ''
			const selectionIdB = rowB.data.find(item => item.columnId === this.id)?.value || null
			const valueB = selectionIdB !== null ? this.selectionOptions.find(item => item.id === selectionIdB)?.label : ''
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(this.getLabel(cell.value), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const cellLabel = this.getLabel(cell.value)
		const filterMethod = {
			[FilterIds.Contains]() { return cellLabel?.includes(filterValue) },
			[FilterIds.BeginsWith]() { return cellLabel?.startsWith(filterValue) },
			[FilterIds.EndsWith]() { return cellLabel?.endsWith(filterValue) },
			[FilterIds.IsEqual]() { return cellLabel === filterValue },
			[FilterIds.IsEmpty]() { return !cellLabel },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
