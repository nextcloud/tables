import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class TextIPv4AddressColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextIPv4Address
		this.textMaxLength = 15
		this.segmentLength = 3
		this.separator = '.'
		this.filler = '0'
	}

	toNaturalSortReadyString(ipV4AddrStr) {
		return ipV4AddrStr.split(this.separator)
							.map(segm => segm.padStart(this.segmentLength, this.filler))
							.join('')
	}

	sort(mode) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			const valueA = rowA.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			const valueB = rowB.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			const valueAForNaturalSort = this.toNaturalSortReadyString(valueA)
			const valueBForNaturalSort = this.toNaturalSortReadyString(valueB)
			return ((valueAForNaturalSort < valueBForNaturalSort) ? -1 : (valueAForNaturalSort > valueBForNaturalSort) ? 1 : 0) * factor
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
		const filterValueForNaturalSort = this.toNaturalSortReadyString(filterValue)
		const cellValueForNaturalSort = this.toNaturalSortReadyString(cellValue)
		if (!cellValue & filter.operator.id !== FilterIds.IsEmpty) return false
		const filterMethod = {
			[FilterIds.Contains]() { return cellValue.includes(filterValue) },
			[FilterIds.BeginsWith]() { return cellValue.startsWith(filterValue) },
			[FilterIds.EndsWith]() { return cellValue.endsWith(filterValue) },
			[FilterIds.IsEqual]() { return cellValue === filterValue },
			[FilterIds.IsGreaterThan]() { return parseInt(cellValueForNaturalSort) > parseInt(filterValueForNaturalSort) },
			[FilterIds.IsGreaterThanOrEqual]() { return parseInt(cellValueForNaturalSort) >= parseInt(filterValueForNaturalSort) },
			[FilterIds.IsLowerThan]() { return parseInt(cellValueForNaturalSort) < parseInt(filterValueForNaturalSort) },
			[FilterIds.IsLowerThanOrEqual]() { return parseInt(cellValueForNaturalSort) <= parseInt(filterValueForNaturalSort) },
			[FilterIds.IsEmpty]() { return !cellValue },
		}[filter.operator.id]

		return super.isFilterFound(filterMethod, cell)

	}

}
