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

	isSearchStringFound(cell, searchString) {
		return false
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ?? filter.value

		// Normalize cell value to boolean
		const cellBoolean = (cell.value === 'true') || (cell.value === true)

		// Handle different filter value formats that might come from magic values
		let filterBoolean
		if (typeof filterValue === 'boolean') {
			filterBoolean = filterValue
		} else if (typeof filterValue === 'string') {
			const normalized = filterValue.toLowerCase().trim()
			filterBoolean = (normalized === 'true') || (normalized === 'yes')
				? true
				: (normalized === 'false') || (normalized === 'no')
					? false
					: Boolean(normalized)
		} else {
			filterBoolean = Boolean(filterValue)
		}

		const filterMethod = {
			[FilterIds.IsEqual]() { return cellBoolean === filterBoolean },
			[FilterIds.IsNotEqual]() { return cellBoolean !== filterBoolean },
			[FilterIds.IsEmpty]() { return cell.value === null || cell.value === undefined || cell.value === '' },
		}[filter.operator.id]
		return filterMethod ? filterMethod() : super.isFilterFound(filterMethod, cell)
	}

}
