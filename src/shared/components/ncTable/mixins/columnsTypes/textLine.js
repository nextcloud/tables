/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractTextColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class TextLineColumn extends AbstractTextColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.TextLine
		this.textMaxLength = data.textMaxLength
		this.textUnique = data.textUnique
	}

	getValueString(valueObject) {
		return valueObject.value.replace(/(<([^>]+)>)/ig, '')
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(cell.value, cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = (filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value).toLowerCase()
		const cellValue = cell.value?.toLowerCase()
		if (!cellValue & filter.operator.id !== FilterIds.IsEmpty) return false
		const filterMethod = {
			[FilterIds.Contains]() { return cellValue.includes(filterValue) },
			[FilterIds.DoesNotContain]() { return !cellValue.includes(filterValue) },
			[FilterIds.BeginsWith]() { return cellValue.startsWith(filterValue) },
			[FilterIds.EndsWith]() { return cellValue.endsWith(filterValue) },
			[FilterIds.IsEqual]() { return cellValue === filterValue },
			[FilterIds.IsNotEqual]() { return cellValue !== filterValue },
			[FilterIds.IsEmpty]() { return !cellValue },
		}[filter.operator.id]

		return super.isFilterFound(filterMethod, cell)

	}

}
