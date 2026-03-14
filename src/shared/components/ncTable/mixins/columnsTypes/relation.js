/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class RelationColumn extends AbstractColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Relation
		this.relationTableId = data.relationTableId
		this.relationMultiple = data.relationMultiple
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null
		const value = valueObject.value
		if (!value) return ''
		try {
			const parsed = JSON.parse(value)
			if (Array.isArray(parsed)) {
				return parsed.map(id => `Row ${id}`).join(', ')
			}
			return `Row ${parsed}`
		} catch {
			return String(value)
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(this.getValueString(cell), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const cellValue = this.getValueString(cell)
		const filterMethod = {
			[FilterIds.Contains]() { return cellValue?.toLowerCase().includes(filterValue?.toLowerCase()) },
			[FilterIds.DoesNotContain]() { return !cellValue?.toLowerCase().includes(filterValue?.toLowerCase()) },
			[FilterIds.IsEmpty]() { return !cellValue },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

	default() {
		return null
	}

	parseValue(value) {
		return value
	}

}
