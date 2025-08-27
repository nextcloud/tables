/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { FilterIds } from '../filter.js'

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
			objects.push(this.getOptionObject(parseInt(id)))
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

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
		const valueString = this.getValueString(cell)

		const filterMethod = {
			[FilterIds.Contains]() { return valueString?.includes(filterValue) },
			[FilterIds.DoesNotContain]() { return !valueString?.includes(filterValue) },
			[FilterIds.IsEqual]() { return valueString === filterValue },
			[FilterIds.IsNotEqual]() { return valueString !== filterValue },
			[FilterIds.IsEmpty]() { return !valueString },
		}[filter.operator.id]
		return super.isFilterFound(filterMethod, cell)
	}

}
