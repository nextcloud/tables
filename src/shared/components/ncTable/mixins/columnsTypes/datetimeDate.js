/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractDatetimeColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import Moment from '@nextcloud/moment'
import { FilterIds } from '../filter.js'

export default class DatetimeDateColumn extends AbstractDatetimeColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.DatetimeDate
	}

	formatValue(value) {
		return Moment(value, 'YYYY-MM-DD HH:mm:ss').format('ll')
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			const tmpA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			const valueA = new Moment(tmpA)
			const tmpB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			const valueB = new Moment(tmpB)
			if (!tmpA && tmpB) {
				return -1 * factor
			}
			if (tmpA && !tmpB) {
				return 1 * factor
			}
			if (!tmpA && !tmpB) {
				return super.getNextSortsResult(nextSorts, rowA, rowB)
			}
			return (valueA.diff(valueB)) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	isSearchStringFound(cell, searchString) {
		const date = new Moment(cell.value, 'YYYY-MM-DD').format('ll')
		return super.isSearchStringFound(date, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const filterDate = new Moment(filterValue)
		const valueDate = new Moment(cell.value)

		const filterMethod = {
			[FilterIds.IsEqual]() { return filterDate.isSame(valueDate) },
			[FilterIds.IsNotEqual]() { return !filterDate.isSame(valueDate) },
			[FilterIds.IsGreaterThan]() { return filterDate.isBefore(valueDate) },
			[FilterIds.IsGreaterThanOrEqual]() { return filterDate.isSameOrBefore(valueDate) },
			[FilterIds.IsLowerThan]() { return filterDate.isAfter(valueDate) },
			[FilterIds.IsLowerThanOrEqual]() { return filterDate.isSameOrAfter(valueDate) },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
