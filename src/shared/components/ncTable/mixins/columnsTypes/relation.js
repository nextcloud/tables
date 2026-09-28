/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractColumn } from '../columnClass.js'
import { useDataStore } from '../../../../../store/data.js'
import { FilterIds } from '../filter.js'
import { ColumnTypes } from '../columnHandler.js'

export default class RelationColumn extends AbstractColumn {

	constructor(col) {
		super(col)
		this.type = ColumnTypes.Relation
		this.subtype = ''
	}

	get allowMultiple() {
		return !!this.customSettings?.allowMultiple
	}

	/**
	 * Format the value for display
	 * @param {unknown} value The value to format
	 * @return {string} The formatted value
	 */
	formatValue(value) {
		const ids = this.normalizeIds(value)
		if (ids.length === 0) {
			return ''
		}
		return ids.map(id => this.getLabel(id) || String(id)).join(', ')
	}

	/**
	 * Parse the value from input
	 * @param {unknown} value The value to parse
	 * @return {number[]} The parsed value
	 */
	parseValue(value) {
		return this.normalizeIds(value)
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null
		const ids = this.normalizeIds(valueObject?.value ?? valueObject)
		return ids.map(id => this.getLabel(id) || String(id)).filter(Boolean).join(', ')
	}

	getLabel(rowId) {
		try {
			const dataStore = useDataStore()
			const columnRelations = dataStore.getRelations(this.id)
			const option = columnRelations[rowId]
			return option ? option.label : ''
		} catch (error) {
			console.warn('Failed to get relation label:', error)
			return ''
		}
	}

	normalizeIds(value) {
		if (value === null || value === undefined || value === '') {
			return []
		}
		const list = Array.isArray(value) ? value : [value]
		return list
			.map(id => parseInt(id))
			.filter(id => !Number.isNaN(id))
	}

	default() {
		return []
	}

	/**
	 * Check if filter matches the cell value
	 * @param {object} cell The cell to check
	 * @param {object} filter The filter to apply
	 * @return {boolean} Whether the filter matches
	 */
	isFilterFound(cell, filter) {
		const rawFilter = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const filterValue = String(rawFilter ?? '').toLowerCase()
		const ids = this.normalizeIds(cell?.value)
		const labels = ids.map(id => (this.getLabel(id) || String(id)).toLowerCase())
		const filterId = Number.parseInt(String(rawFilter), 10)
		const filterIsNumericId = String(rawFilter) === String(filterId) && !Number.isNaN(filterId)
		const hasId = filterIsNumericId && ids.includes(filterId)
		const hasExactLabel = labels.includes(filterValue)
		const hasPartialLabel = labels.some(label => label.includes(filterValue))
		const allowMultiple = this.allowMultiple

		const filterMethod = {
			[FilterIds.Contains]() { return hasId || hasPartialLabel },
			[FilterIds.DoesNotContain]() { return !hasId && !hasPartialLabel },
			[FilterIds.IsEqual]() {
				if (filterIsNumericId) {
					return allowMultiple ? ids.includes(filterId) : (ids.length === 1 && ids[0] === filterId)
				}
				return allowMultiple ? hasExactLabel : (labels.length === 1 && labels[0] === filterValue)
			},
			[FilterIds.IsNotEqual]() {
				if (filterIsNumericId) {
					return allowMultiple ? !ids.includes(filterId) : !(ids.length === 1 && ids[0] === filterId)
				}
				return allowMultiple ? !hasExactLabel : !(labels.length === 1 && labels[0] === filterValue)
			},
			[FilterIds.IsEmpty]() { return ids.length === 0 },
			[FilterIds.IsNotEmpty]() { return ids.length > 0 },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

	isSearchStringFound(cell, searchString) {
		const value = this.getValueString(cell)
		return super.isSearchStringFound(value, cell, searchString)
	}

}
