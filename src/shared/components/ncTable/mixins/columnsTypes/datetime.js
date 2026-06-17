/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractDatetimeColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import Moment from '@nextcloud/moment'
import { FilterIds } from '../filter.js'
import {
	TYPE_META_CREATED_AT,
	TYPE_META_UPDATED_AT,
} from '../../../../constants.ts'

export default class DatetimeColumn extends AbstractDatetimeColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Datetime
	}

	formatValue(value) {
		return Moment(value, 'YYYY-MM-DD HH:mm:ss').format('lll')
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			let tmpA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			let tmpB = rowB.data.find(item => item.columnId === this.id)?.value || ''
			if (this.id === TYPE_META_CREATED_AT) {
				tmpA = rowA.createdAt
				tmpB = rowB.createdAt
			}
			if (this.id === TYPE_META_UPDATED_AT) {
				tmpA = rowA.lastEditAt
				tmpB = rowB.lastEditAt
			}

			if (!tmpA && tmpB) {
				return -1 * factor
			}
			if (tmpA && !tmpB) {
				return 1 * factor
			}
			if (!tmpA && !tmpB) {
				return super.getNextSortsResult(nextSorts, rowA, rowB)
			}

			const valueA = new Moment(tmpA, 'YYYY-MM-DD HH:mm')
			const valueB = new Moment(tmpB, 'YYYY-MM-DD HH:mm')
			return (valueA.diff(valueB)) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	isSearchStringFound(cell, searchString) {
		const date = new Moment(cell.value, 'YYYY-MM-DD HH:mm').format('lll')
		return super.isSearchStringFound(date, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const filterDate = new Moment(filterValue, 'YYYY-MM-DD HH:mm')
		const valueDate = new Moment(cell.value, 'YYYY-MM-DD HH:mm')

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
