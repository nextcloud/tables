/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'
import { selectionOptionId } from '../selectionReference.js'

export default class SelectionMutliColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.SelectionMulti
		this.selectionOptions = data.selectionOptions
	}

	default() {
		if (!this.selectionDefault) {
			return []
		}
		return JSON.parse(this.selectionDefault)
	}

	getValueString(valueObject) {
		valueObject = valueObject || this.value || null

		const valueObjects = this.getObjects(valueObject.value)
		let ret = ''
		valueObjects?.forEach(obj => {
			if (ret === '') {
				ret = obj.label
			} else {
				ret += ', ' + obj.label
			}
		})
		return ret
	}

	getDefaultObjects() {
		return this.getObjects(this.default())
	}

	getObjects(values) {
		// values is an array of option-ids as string
		const objects = []
		values?.forEach(id => {
			const optionsObject = this.getOptionObject(parseInt(id))
			// skip options that not exists anymore
			if (optionsObject) {
				objects.push(optionsObject)
			}
		})
		return objects
	}

	getOptionObject(id) {
		const i = this.selectionOptions?.findIndex(obj => {
			return obj.id === id
		})
		if (i !== undefined) {
			return this.selectionOptions[i] || null
		}
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(this.getValueString(cell), cell, searchString)
	}

	/**
	 * @param {object} cell the row cell to test
	 * @param {object} filter the stored filter or condition
	 * @return {boolean}
	 */
	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const referencedId = selectionOptionId(filterValue)
		if (referencedId !== null) {
			const selected = Array.isArray(cell.value) ? cell.value : []
			const holdsOnly = selected.length === 1 && selected[0] === referencedId
			const byReference = {
				[FilterIds.Contains]: () => selected.includes(referencedId),
				[FilterIds.DoesNotContain]: () => !selected.includes(referencedId),
				[FilterIds.IsEqual]: () => holdsOnly,
				[FilterIds.IsNotEqual]: () => !holdsOnly,
			}[filter.operator.id]
			if (byReference) {
				return super.isFilterFound(byReference, cell)
			}
		}
		const valueString = this.getValueString(cell)?.toLowerCase()

		const filterMethod = {
			[FilterIds.ContainsItem]() {
				if ((!cell.value || !cell.value.length) && filter.value.length > 0) {
					return false
				}
				const filterOptionIds = filter.value.map(option => option.id)
				return cell.value.filter(v => filterOptionIds.includes(v)).length > 0
			},
			[FilterIds.Contains]() { return valueString?.includes(filterValue.toLowerCase()) },
			[FilterIds.DoesNotContain]() { return !valueString?.includes(filterValue.toLowerCase()) },
			[FilterIds.IsEqual]() { return valueString === filterValue.toLowerCase() },
			[FilterIds.IsNotEqual]() { return valueString !== filterValue.toLowerCase() },
			[FilterIds.IsEmpty]() { return !valueString },
			[FilterIds.IsNotEmpty]() { return !!valueString },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
