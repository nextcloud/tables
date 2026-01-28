/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractColumn } from '../columnClass.js'
import { useDataStore } from '../../../../../store/data.js'
import { useTablesStore } from '../../../../../store/store.js'
import { FilterIds } from '../filter.js'

export default class RelationColumn extends AbstractColumn {

	constructor(col) {
		super(col)
		this.type = 'relation'
		this.subtype = ''
	}

	/**
	 * Format the value for display
	 * @param {any} value The value to format
	 * @return {string} The formatted value
	 */
	formatValue(value) {
		if (value === null || value === undefined) {
			return ''
		}
		// For single relations, return the value as is
		return String(value)
	}

	/**
	 * Parse the value from input
	 * @param {any} value The value to parse
	 * @return {any} The parsed value
	 */
	parseValue(value) {
		if (value === null || value === undefined || value === '') {
			return null
		}
		// For single relations, return the value as is
		return value
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null
		return this.getLabel(valueObject.value)
	}

	getLabel(rowId) {
		// Try to get relation data from the store
		try {
			const tablesStore = useTablesStore()
			const dataStore = useDataStore()

			const activeElement = tablesStore.activeView || tablesStore.activeTable
			if (!activeElement) {
				return ''
			}

			const columnRelations = dataStore.getRelations(this.id)
			const option = columnRelations[rowId]

			return option ? option.label : undefined
		} catch (error) {
			console.warn('Failed to get relation label:', error)
			return ''
		}
	}

	default() {
		return null
	}

	/**
	 * Check if filter matches the cell value
	 * @param {any} cell The cell to check
	 * @param {any} filter The filter to apply
	 * @return {boolean} Whether the filter matches
	 */
	isFilterFound(cell, filter) {
		const filterMethod = {
			[FilterIds.IsNotEmpty]() { return cell.value !== null && cell.value !== undefined && cell.value !== '' },
			[FilterIds.IsEmpty]() { return cell.value === null || cell.value === undefined || cell.value === '' },
			[FilterIds.IsEqual]() { return cell.value === filter.value },
			[FilterIds.IsNotEqual]() { return cell.value !== filter.value },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

	isSearchStringFound(cell, searchString) {
		const value = this.getValueString(cell)
		return super.isSearchStringFound(value, cell, searchString)
	}

}
