export default {

	methods: {

		sortingNumber(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const valueA = rowA.data.find(item => item.columnId === column.id)?.value || null
				const valueB = rowB.data.find(item => item.columnId === column.id)?.value || null
				if (!valueA && valueB) {
					return -1 * factor
				}
				if (valueA && !valueB) {
					return 1 * factor
				}
				if (!valueA && !valueB) {
					return 0
				}
				return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
			}
		},
		isSearchStringFoundForNumber(column, cell, searchString) {
			if (('' + cell.value).toLowerCase().includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForNumber(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
			if (filter.operator === 'is-equal' && parseFloat(cell.value) === parseFloat(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than' && parseFloat(cell.value) > parseFloat(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than-or-equal' && parseFloat(cell.value) >= parseFloat(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than' && parseFloat(cell.value) < parseFloat(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than-or-equal' && parseFloat(cell.value) <= parseFloat(filterValue)) {
				cell.filterFound = true
				return true
			}
			return false
		},

	},

}
