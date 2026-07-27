/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractDatetimeColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import Moment from '@nextcloud/moment'
import { FilterIds } from '../filter.js'

export default class DatetimeTimeColumn extends AbstractDatetimeColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.DatetimeTime
	}

	formatValue(value) {
		return Moment(value, 'HH:mm:ss').format('LT')
	}

	isSearchStringFound(cell, searchString) {
		const time = new Moment(cell.value, 'YYYY-MM-DD HH:mm').format('lll')
		return super.isSearchStringFound(time, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const filterTime = new Moment(filterValue, 'HH:mm')
		const valueTime = new Moment(cell.value, 'HH:mm')

		const filterMethod = {
			[FilterIds.IsEqual]() { return filterTime.isSame(valueTime) },
			[FilterIds.IsNotEqual]() { return !filterTime.isSame(valueTime) },
			[FilterIds.IsGreaterThan]() { return filterTime.isBefore(valueTime) },
			[FilterIds.IsGreaterThanOrEqual]() { return filterTime.isSameOrBefore(valueTime) },
			[FilterIds.IsLowerThan]() { return filterTime.isAfter(valueTime) },
			[FilterIds.IsLowerThanOrEqual]() { return filterTime.isSameOrAfter(valueTime) },
			[FilterIds.IsEmpty]() { return !cell.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
