export default {

	methods: {
		getValueStringForTextLong(valueObject) {
			return valueObject.value.replace(/(<([^>]+)>)/ig, '')
		},
		isSearchStringFoundForTextLong(column, cell, searchString) {
			if (cell.value.toLowerCase().includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForTextLong(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

			if (filter.operator === 'contains' && cell.value.includes(filterValue)) {
				cell.filterFound = true
				return true
			}
			return false
		},

	},
}
