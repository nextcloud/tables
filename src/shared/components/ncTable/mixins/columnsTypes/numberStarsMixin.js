export default {

	methods: {

		sortingNumberStars(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const tmpA = rowA.data.find(item => item.columnId === column.id)?.value
				const valueA = parseInt(tmpA)
				const tmpB = rowB.data.find(item => item.columnId === column.id)?.value
				const valueB = parseInt(tmpB)
				return ((valueA < valueB) ? 1 : (valueA > valueB) ? -1 : 0) * factor
			}
		},
		isSearchStringFoundForNumberStars(column, cell, searchString) {
			if (('' + cell.value).includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForNumberStars(column, cell, filter) {
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
			return false
		},

	},

}
