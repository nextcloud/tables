/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { useDataStore } from '../../../../../store/data.js'

export default class LookupColumn extends AbstractColumn {

	constructor(col) {
		super(col)
		this.type = ColumnTypes.Lookup
		this.subtype = ''
	}

	/**
	 * Format the value for display
	 * @param {unknown} value The value to format
	 * @return {string} The formatted value
	 */
	formatValue(value) {
		if (value === null || value === undefined) {
			return ''
		}
		// Return the virtual value as is - it comes from the related table
		return String(value)
	}

	/**
	 * Parse the value from input
	 * @param {unknown} value The value to parse
	 * @return {unknown} The parsed value
	 */
	parseValue(value) {
		// This is a virtual column, no input parsing needed
		// Values come from related rows via backend
		return value
	}

	/**
	 * The lookup value is sourced from the referenced relation column's cell.
	 * @return {number} The relation column id to read the cell value from
	 */
	getValueColumnId() {
		return this.customSettings?.relationColumnId
	}

	resolveValue(value) {
		const rowId = value !== null && typeof value === 'object' ? value.value : value
		const data = useDataStore().getRelations(this.id)
		const column = data.column ?? null
		const row = data.values?.[rowId] ?? null
		let status = 'unavailable'
		if (data.loading) status = 'loading'
		else if (data.column === null) status = 'unavailable'
		else if (rowId === null || rowId === undefined || rowId === '') status = 'empty'
		else if (column && row) status = row.value === null || row.value === undefined ? 'empty' : 'ready'
		return { column, row, status, value: status === 'ready' ? column.parseValue(row.value) : null }
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null
		// Resolve the lookup through its source relation cell
		const lookup = this.resolveValue(valueObject?.value)
		if (lookup.status === 'ready') {
			return lookup.column.getValueString({ value: lookup.row.value }) ?? ''
		}
		// Unavailable lookup data must not be exported as a source row ID
		return ''
	}

	default() {
		// Virtual columns don't have default values
		return null
	}

	/**
	 * Check if filter matches the cell value
	 * @param {unknown} cell The cell to check
	 * @param {unknown} filter The filter to apply
	 * @return {boolean} Whether the filter matches
	 */
	isFilterFound(cell, filter) {
		return false
	}

	isSearchStringFound(cell, searchString) {
		const lookup = this.resolveValue(cell?.value)
		const found = lookup.status === 'ready'
			&& lookup.column.isSearchStringFound({ value: lookup.row.value }, searchString)
		if (found) cell.searchStringFound = true
		return !!found
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		const values = new WeakMap()
		let targetComparator
		const lookupForRow = row => {
			if (!values.has(row)) {
				const lookup = this.resolveValue(row.data.find(item => item.columnId === this.getValueColumnId())?.value)
				lookup.sortRow = { ...row, data: [{ columnId: lookup.column?.id, value: lookup.row?.value }] }
				values.set(row, lookup)
			}
			return values.get(row)
		}
		return (rowA, rowB) => {
			const lookupA = lookupForRow(rowA)
			const lookupB = lookupForRow(rowB)
			const valueA = lookupA.status === 'ready' ? lookupA.value : null
			const valueB = lookupB.status === 'ready' ? lookupB.value : null
			let result = 0
			if (valueA === null || valueB === null) {
				result = (valueA === null ? -1 : 1) * factor
				if (valueA === valueB) result = 0
			} else if (lookupA.column.type.startsWith('number')) {
				result = (valueA - valueB) * factor
			} else if (lookupA.column.canSort()) {
				targetComparator ??= lookupA.column.sort(mode)
				result = targetComparator(lookupA.sortRow, lookupB.sortRow)
			} else {
				// Compare display strings for target types without a comparator
				const labelA = String(lookupA.column.getValueString({ value: lookupA.row.value }) ?? '').toLowerCase()
				const labelB = String(lookupB.column.getValueString({ value: lookupB.row.value }) ?? '').toLowerCase()
				result = labelA.localeCompare(labelB) * factor
			}
			return result || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

}
