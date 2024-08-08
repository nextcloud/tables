/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class SelectionCheckColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.SelectionCheck
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? 1 : -1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			const valueA = (tmpA === true || tmpA === 'true')
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			const valueB = (tmpB === true || tmpB === 'true')
			return (valueA - valueB) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	isSearchStringFound(cell, searchString) {
		return false
	}

	isFilterFound(cell, filter) {
		const filterValue = '' + filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[FilterIds.IsEqual]() { return (cell.value === 'true' && filterValue === 'yes') || (cell.value === 'false' && filterValue === 'no') },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
