/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractColumn } from '../columnClass.js'
import { useDataStore } from '../../../../../store/data.js'

export default class RelationLookupColumn extends AbstractColumn {

	constructor(col) {
		super(col)
		this.type = 'relation_lookup'
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
		// Return the virtual value as is - it comes from the related table
		return String(value)
	}

	/**
	 * Parse the value from input
	 * @param {any} value The value to parse
	 * @return {any} The parsed value
	 */
	parseValue(value) {
		// This is a virtual column, no input parsing needed
		// Values come from related rows via backend
		return value
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null
		if (!valueObject || valueObject.value === null || valueObject.value === undefined) {
			return ''
		}

		// Check if we have relation data to get the label
		if (this.customSettings?.targetColumnId) {
			try {
				const dataStore = useDataStore()
				const relationData = dataStore.getRelations(this.id)

				let value = valueObject.value
				if (typeof value === 'object' && value !== null) {
					value = value.value
				}

				if (relationData?.data && value) {
					// Get the related row data for the current value (which is the relation ID)
					const relatedRow = relationData.data[value]
					if (relatedRow) {
						return relationData.column.getValueString(relatedRow.label)
					}
				}
			} catch (error) {
				console.warn('Failed to get relation supplement label:', error)
			}
		}

		// Fallback to original behavior
		return String(valueObject.value)
	}

	default() {
		// Virtual columns don't have default values
		return null
	}

	/**
	 * Check if filter matches the cell value
	 * @param {any} cell The cell to check
	 * @param {any} filter The filter to apply
	 * @return {boolean} Whether the filter matches
	 */
	isFilterFound(cell, filter) {
		return false
	}

	isSearchStringFound(rowData, cell, searchString) {
		const dataStore = useDataStore()
		const relationLookup = dataStore.getRelations(this.id)
		const cellRelationLookup = rowData?.find(item => item.columnId === this.customSettings.relationColumnId)
		if (!cellRelationLookup) return ''

		let value = ''
		if (relationLookup.data[cellRelationLookup.value].label) {
			value = relationLookup.column.isSearchStringFound({ value: relationLookup.data[cellRelationLookup.value].label }, searchString)
		}

		return value
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			let valueA = rowA.data.find(item => item.columnId === this.id)?.value || ''
			let valueB = rowB.data.find(item => item.columnId === this.id)?.value || ''

			// Convert to strings for comparison
			valueA = String(valueA).toLowerCase()
			valueB = String(valueB).toLowerCase()

			return valueA.localeCompare(valueB, undefined) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

}
