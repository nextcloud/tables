/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class TextLongColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextLong
	}

	getValueString(valueObject) {
		return valueObject.value.replace(/(<([^>]+)>)/ig, '')
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(cell.value, cell, searchString)
	}

	getFilterMethods(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		return {
			[FilterIds.Contains]() { return cell.value.includes(filterValue) },
			[FilterIds.DoesNotContain]() { return !cell.value.includes(filterValue) },
			[FilterIds.IsEmpty]() { return !cell.value },
		}
	}

}
