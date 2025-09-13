/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { Filters } from './filter.js'
import { MagicFields } from './magicFields.js'

export class AbstractColumn {

	type = null

	constructor(data) {
		this.createdAt = data.createdAt
		this.createdBy = data.createdBy
		this.id = data.id
		this.lastEditAt = data.lastEditAt
		this.lastEditBy = data.lastEditBy
		this.mandatory = data.mandatory
		this.tableId = data.tableId
		this.title = data.title
		this.description = data.description
		this.createdByDisplayName = data.createdByDisplayName
		this.viewColumnInformation = data.viewColumnInformation
		this.lastEditByDisplayName = data.lastEditByDisplayName
		this.customSettings = data.customSettings
	}

	canSort() {
		return typeof this.sort === 'function'
	}

	getNextSortsResult(nextSorts, rowA, rowB) {
		if (!nextSorts) {
			return 0
		}
		for (const sort of nextSorts) {
			const result = sort(rowA, rowB)
			if (result !== 0) {
				return result
			}
		}
		return 0
	}

	getPossibleOperators() {
		return Object.values(Filters).filter(fil => fil.goodFor.includes(this.type))
	}

	getPossibleMagicFields() {
		return Object.values(MagicFields).filter(item => item.goodFor.includes(this.type))
	}

	isSearchStringFound(cellValue, cell, searchString) {
		if (cellValue != null && cellValue.toLowerCase().includes(searchString)) {
			cell.searchStringFound = true
			return true
		}
		return false
	}

	isFilterFound(filterMethod, cell) {
		if (filterMethod()) {
			cell.filterFound = true
			return true
		}
		return false
	}

	getValueString(valueObject) {
		return valueObject.value
	}

	/**
	 * parse an input value
	 *
	 * @param {*} value Value to parse
	 * @return {*}
	 */
	parseValue(value) {
		return value
	}

}

export class AbstractUsergroupColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.usergroupDefault = data.usergroupDefault
	}

	default() {
		return this.usergroupDefault
	}

}

export class AbstractNumberColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.numberDefault = data.numberDefault
	}

	default() {
		return this.numberDefault
	}

}

export class AbstractDatetimeColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.datetimeDefault = data.datetimeDefault
	}

	formatValue(value) {}

	default() {
		return this.datetimeDefault
	}

}

export class AbstractTextColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.textDefault = data.textDefault
	}

	default() {
		return this.textDefault
	}

}

export class AbstractSelectionColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.selectionDefault = data.selectionDefault
	}

	default() {
		return this.selectionDefault
	}

}
