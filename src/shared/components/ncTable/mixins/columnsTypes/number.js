/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractNumberColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'
import { TYPE_META_ID } from '../../../../constants.ts'

export default class NumberColumn extends AbstractNumberColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Number
		this.numberDecimals = data.numberDecimals
		this.numberMax = data.numberMax
		this.numberMin = data.numberMin
		this.numberPrefix = data.numberPrefix
		this.numberSuffix = data.numberSuffix
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			let valueA = rowA.data.find(item => item.columnId === this.id)?.value || null
			let valueB = rowB.data.find(item => item.columnId === this.id)?.value || null
			if (this.id === TYPE_META_ID) {
				valueA = rowA.id
				valueB = rowB.id
			}
			if (!valueA && valueB) {
				return -1 * factor
			}
			if (valueA && !valueB) {
				return 1 * factor
			}
			if (!valueA && !valueB) {
				return super.getNextSortsResult(nextSorts, rowA, rowB)
			}
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(('' + cell.value), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[FilterIds.IsEqual]() { return parseInt(cell.value) === parseInt(filterValue) },
			[FilterIds.IsNotEqual]() { return parseInt(cell.value) !== parseInt(filterValue) },
			[FilterIds.IsGreaterThan]() { return parseInt(cell.value) > parseInt(filterValue) },
			[FilterIds.IsGreaterThanOrEqual]() { return parseInt(cell.value) >= parseInt(filterValue) },
			[FilterIds.IsLowerThan]() { return parseInt(cell.value) < parseInt(filterValue) },
			[FilterIds.IsLowerThanOrEqual]() { return parseInt(cell.value) <= parseInt(filterValue) },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

	parseValue(value) {
		return value === null ? null : parseFloat(value)
	}

}
