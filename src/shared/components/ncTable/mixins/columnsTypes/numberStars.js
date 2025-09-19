/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
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
			const valueA = Number.isNaN(parseInt(tmpA)) ? -1 : parseInt(tmpA)
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value
			const valueB = Number.isNaN(parseInt(tmpB)) ? -1 : parseInt(tmpB)
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(('' + cell.value), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[FilterIds.IsEqual]() { return parseInt(cell.value ? cell.value : 0) === parseInt(filterValue) },
			[FilterIds.IsNotEqual]() { return parseInt(cell.value ? cell.value : 0) !== parseInt(filterValue) },
			[FilterIds.IsGreaterThan]() { return parseInt(cell.value ? cell.value : 0) > parseInt(filterValue) },
			[FilterIds.IsGreaterThanOrEqual]() { return parseInt(cell.value ? cell.value : 0) >= parseInt(filterValue) },
			[FilterIds.IsLowerThan]() { return parseInt(cell.value ? cell.value : 0) < parseInt(filterValue) },
			[FilterIds.IsLowerThanOrEqual]() { return parseInt(cell.value ? cell.value : 0) <= parseInt(filterValue) },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
