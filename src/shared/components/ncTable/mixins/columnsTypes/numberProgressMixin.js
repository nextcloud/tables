export default {

	methods: {

		sortingNumberProgress(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const tmpA = rowA.data.find(item => item.columnId === column.id)?.value || null
				const valueA = parseInt(tmpA)
				const tmpB = rowB.data.find(item => item.columnId === column.id)?.value || null
				const valueB = parseInt(tmpB)
				return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
			}
		},
		isSearchStringFoundForNumberProgress(column, cell, searchString) {
			if (('' + cell.value).toLowerCase().includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForNumberProgress(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

			if (filter.operator === 'is-equal' && parseInt(cell.value) === parseInt(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than' && parseInt(cell.value) > parseInt(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than-or-equal' && parseInt(cell.value) >= parseInt(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than' && parseInt(cell.value) < parseInt(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than-or-equal' && parseInt(cell.value) <= parseInt(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-empty' && !cell.value) {
				cell.filterFound = true
				return true
			}
			return false
		},

	},

}
