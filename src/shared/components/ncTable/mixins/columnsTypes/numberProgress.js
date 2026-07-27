/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractNumberColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class NumberProgressColumn extends AbstractNumberColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.NumberProgress
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
		return parseInt(value)
	}

}
