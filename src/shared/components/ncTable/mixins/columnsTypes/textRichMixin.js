export default {

	methods: {
		isSearchStringFoundForTextRich(column, cell, searchString) {
			if (cell.value.toLowerCase().includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForTextRich(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

			if (filter.operator === 'contains' && cell.value.includes(filterValue)) {
				cell.filterFound = true
				return true
			}
			return false
		},

	},
}
