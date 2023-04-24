export default {

	methods: {
		sortingTextLink(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const valueA = rowA.data.find(item => item.columnId === column.id)?.value || ''
				const valueB = rowB.data.find(item => item.columnId === column.id)?.value || ''
				return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
			}
		},
		isSearchStringFoundForTextLink(column, cell, searchString) {
			if (cell.value.includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForTextLink(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
			if (filter.operator === 'contains' && cell.value.includes(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'begins-with' && cell.value.startsWith(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'ends-with' && cell.value.endsWith(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-equal' && cell.value === filterValue) {
				cell.filterFound = true
				return true
			}
			return false
		},
	},
}
