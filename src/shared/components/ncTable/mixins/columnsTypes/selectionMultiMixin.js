export default {

	methods: {
		getValueStringForSelectionMulti(valueObject, column) {
			column = column || this.column || null
			valueObject = valueObject || this.value || null

			const valueObjects = this.getObjectsForSelectionMulti(valueObject.value, column)
			let ret = ''
			valueObjects?.forEach(obj => {
				if (ret === '') {
					ret = obj.label
				} else {
					ret += ', ' + obj.label
				}
			})
			return ret
		},

		getObjectsForSelectionMulti(values, column) {
			// values is an array of option-ids as string

			const objects = []
			values.forEach(id => {
				objects.push(this.getOptionObjectForSelectionMulti(parseInt(id), column))
			})
			return objects
		},

		getOptionObjectForSelectionMulti(id, column) {
			const i = column?.selectionOptions?.findIndex(obj => {
				return obj.id === id
			})
			if (i !== undefined) {
				return column?.selectionOptions[i] || null
			}
		},

		getDefaultObjectsForSelectionMulti(column) {
			column = column || this.column || null

			if (!column?.selectionDefault) {
				return []
			}

			const defaultObjects = []
			JSON.parse(column?.selectionDefault)?.forEach(id => {
				defaultObjects.push(this.getOptionObjectForSelectionMulti(parseInt(id), column))
			})
			return defaultObjects
		},

	},

}
