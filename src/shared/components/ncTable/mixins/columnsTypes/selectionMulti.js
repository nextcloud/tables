import { AbstractSelectionColumn } from '../columnClass.js'
import { ColumnTypes } from '../columnHandler.js'
import { Filters } from '../filter.js'

export default class SelectionMutliColumn extends AbstractSelectionColumn {

	constructor(data) {
		super(data)
		this.type = ColumnTypes.SelectionMulti
		this.selectionOptions = data.selectionOptions
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

	getDefaultObjects() {
		if (!this.selectionDefault) {
			return []
		}

		const defaultObjects = []
		JSON.parse(this.selectionDefault)?.forEach(id => {
			defaultObjects.push(this.getOptionObjectForSelectionMulti(parseInt(id), this))
		})
		return defaultObjects
	}

	isSearchStringFound(cell, searchString) {
		return super.isSearchStringFound(this.getValueString(cell), cell, searchString)
	}

	isFilterFound(cell, filter) {
		const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

		const filterMethod = {
			[Filters.Contains.id]() { return this.getValueString(cell)?.includes(filterValue) },
			[Filters.IsEqual.id]() { return this.getValueString(cell) === filterValue },
		}[filter.operator]
		return super.isFilterFound(filterMethod, cell)
	}

}
