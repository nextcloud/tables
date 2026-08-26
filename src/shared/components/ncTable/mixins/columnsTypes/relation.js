/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractColumn } from '../columnClass.js'
import { useDataStore } from '../../../../../store/data.js'
import { useTablesStore } from '../../../../../store/store.js'
import { ColumnTypes } from '../columnHandler.js'

export default class RelationColumn extends AbstractColumn {

	constructor(col) {
		super(col)
		this.type = ColumnTypes.Relation
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
		// For single relations, return the value as is
		return String(value)
	}

	/**
	 * Parse the value from input
	 * @param {unknown} value The value to parse
	 * @return {unknown} The parsed value
	 */
	parseValue(value) {
		if (value === null || value === undefined || value === '') {
			return null
		}
		// For single relations, return the value as is
		return value
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

}
