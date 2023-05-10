export default {

	methods: {
		sortingSelectionCheck(column, mode) {
			const factor = mode === 'desc' ? 1 : -1
			return function(rowA, rowB) {
				const tmpA = rowA.data.find(item => item.columnId === column.id)?.value || ''
				const valueA = (tmpA === true || tmpA === 'true')
				const tmpB = rowB.data.find(item => item.columnId === column.id)?.value || ''
				const valueB = (tmpB === true || tmpB === 'true')
				return (valueA - valueB) * factor
			}
		},
		isFilterFoundForSelectionCheck(column, cell, filter) {
			const yesPossibilities = ['yes', 'true', 'check', 'checked', 'y']
			const noPossibilities = ['no', 'false', 'unchecked', 'uncheck', 'n']
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

			if (filter.operator === 'is-equal' && cell.value === 'true' && yesPossibilities.findIndex(item => item === filterValue) !== -1) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-equal' && cell.value === 'false' && noPossibilities.findIndex(item => item === filterValue) !== -1) {
				cell.filterFound = true
				return true
			}
			return false
		},
	},
}
