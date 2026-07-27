/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractDatetimeColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import Moment from '@nextcloud/moment'
import { FilterIds } from '../filter.js'

export default class DatetimeColumn extends AbstractDatetimeColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Datetime
	}

	formatValue(value) {
		return Moment(value, 'YYYY-MM-DD HH:mm:ss').format('lll')
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
