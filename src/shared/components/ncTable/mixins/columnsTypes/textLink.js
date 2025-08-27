/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class TextLinkColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextLink
		this.textAllowedPattern = data.textAllowedPattern
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			const valueA = this.getValueFromCellValue(tmpA)
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value?.toLowerCase() || ''
			const valueB = this.getValueFromCellValue(tmpB)
			return valueA.localeCompare(valueB, undefined) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	getValueFromCellValue(cellValue) {
		// check if the value is the old string or the new object
		try {
			const parseResult = JSON.parse(cellValue)
			return parseResult?.title || ''
		} catch (err) {
			// old string value
			return cellValue
		}
	}

	getValueString(valueObject) {
		try {
			const parseResult = JSON.parse(valueObject.value)
			const link = parseResult?.resourceUrl ? ' (' + parseResult?.resourceUrl + ')' : ''
			return parseResult?.title + link || ''
		} catch (err) {
			// old string value
			return valueObject.value
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(cell.value, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const value = this.getValueFromCellValue(cell.value)

		const filterMethod = {
			[FilterIds.Contains]() { return value.includes(filterValue) },
			[FilterIds.DoesNotContain]() { return !value.includes(filterValue) },
			[FilterIds.BeginsWith]() { return value.startsWith(filterValue) },
			[FilterIds.EndsWith]() { return value.endsWith(filterValue) },
			[FilterIds.IsEqual]() { return value === filterValue },
			[FilterIds.IsNotEqual]() { return value !== filterValue },
			[FilterIds.IsEmpty]() { return !value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
