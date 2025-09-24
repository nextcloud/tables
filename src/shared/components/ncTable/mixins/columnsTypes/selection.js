/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

export default class SelectionColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.Selection
		this.selectionOptions = data.selectionOptions
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null
		return this.getLabel(valueObject.value)
	}

	getLabel(id) {
		const i = this.selectionOptions?.findIndex((obj) => obj.id === id)
		return this.selectionOptions[i]?.label
	}

	isDeletedLabel(value) {
		const i = this.selectionOptions?.findIndex((obj) => obj.id === value)
		return !!this.selectionOptions[i]?.deleted
	}

	sort(mode, nextSorts) {
		const factor = mode === 'DESC' ? -1 : 1
		return (rowA, rowB) => {
			const selectionIdA = parseInt(rowA.data.find(item => item.columnId === this.id)?.value)
			const vA = Number.isNaN(selectionIdA) ? '' : this.selectionOptions.find(item => item.id === selectionIdA)?.label
			const valueA = this.removeEmoji(vA).trim()
			const selectionIdB = parseInt(rowB.data.find(item => item.columnId === this.id)?.value ?? null)
			const vB = Number.isNaN(selectionIdB) ? '' : this.selectionOptions.find(item => item.id === selectionIdB)?.label
			const valueB = this.removeEmoji(vB).trim()
			return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor || super.getNextSortsResult(nextSorts, rowA, rowB)
		}
	}

	removeEmoji(str) {
		return str.replace(/([#0-9]\u20E3)|[\xA9\xAE\u203C\u2047-\u2049\u2122\u2139\u3030\u303D\u3297\u3299][\uFE00-\uFEFF]?|[\u2190-\u21FF][\uFE00-\uFEFF]?|[\u2300-\u23FF][\uFE00-\uFEFF]?|[\u2460-\u24FF][\uFE00-\uFEFF]?|[\u25A0-\u25FF][\uFE00-\uFEFF]?|[\u2600-\u27BF][\uFE00-\uFEFF]?|[\u2900-\u297F][\uFE00-\uFEFF]?|[\u2B00-\u2BF0][\uFE00-\uFEFF]?|(?:\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDEFF])[\uFE00-\uFEFF]?/g, '')
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(this.getLabel(cell.value), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const cellLabel = this.getLabel(cell.value)
		const filterMethod = {
			[FilterIds.Contains]() { return cellLabel?.toLowerCase().includes(filterValue?.toLowerCase()) },
			[FilterIds.DoesNotContain]() { return !cellLabel?.toLowerCase().includes(filterValue?.toLowerCase()) },
			[FilterIds.BeginsWith]() { return cellLabel?.startsWith(filterValue) },
			[FilterIds.EndsWith]() { return cellLabel?.endsWith(filterValue) },
			[FilterIds.IsEqual]() { return cellLabel === filterValue },
			[FilterIds.IsNotEqual]() { return cellLabel !== filterValue },
			[FilterIds.IsEmpty]() { return !cellLabel },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

	parseValue(value) {
		return parseInt(value)
	}

}
