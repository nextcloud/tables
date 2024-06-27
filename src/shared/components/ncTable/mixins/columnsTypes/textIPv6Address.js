import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class TextIPv6AddressColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextIPv6Address
		this.textMaxLength = 39
		this.segmentLength = 4
		this.separator = ':'
		this.filler = '0'
	}

	toNaturalSortReadyString(ipV6AddrStr) {
		return ipV6AddrStr.split(this.separator)
							.map(segm => segm.padStart(this.segmentLength, this.filler))
							.join('')
	}

	compareAlphaNumStr(referenceStr, compareStr) {
		// This is a wrapper for https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/localeCompare#numeric_sorting
		return referenceStr.localeCompare(compareStr, undefined, { numeric: true })
	}

	sort(mode) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			const valueA = rowA.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			const valueB = rowB.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			const valueAForNaturalSort = this.toNaturalSortReadyString(valueA)
			const valueBForNaturalSort = this.toNaturalSortReadyString(valueB)
			return this.compareAlphaNumStr(valueAForNaturalSort, valueBForNaturalSort) * factor
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
			[FilterIds.IsGreaterThan]() { return this.compareAlphaNumStr(cellValueForNaturalSort, filterValueForNaturalSort) > 0 },
			[FilterIds.IsGreaterThanOrEqual]() { return this.compareAlphaNumStr(cellValueForNaturalSort, filterValueForNaturalSort) >= 0 },
			[FilterIds.IsLowerThan]() { return this.compareAlphaNumStr(cellValueForNaturalSort, filterValueForNaturalSort) < 0 },
			[FilterIds.IsLowerThanOrEqual]() { return this.compareAlphaNumStr(cellValueForNaturalSort, filterValueForNaturalSort) <= 0 },
			[FilterIds.IsEmpty]() { return !cellValue },
		}[filter.operator.id]

		return super.isFilterFound(filterMethod, cell)

	}

}
