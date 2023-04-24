import Moment from '@nextcloud/moment'

export default {

	methods: {
		sortingDatetimeDate(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const tmpA = rowA.data.find(item => item.columnId === column.id)?.value || ''
				const valueA = new Moment(tmpA)
				const tmpB = rowB.data.find(item => item.columnId === column.id)?.value || ''
				const valueB = new Moment(tmpB)
				if (!tmpA && tmpB) {
					return -1 * factor
				}
				if (tmpA && !tmpB) {
					return 1 * factor
				}
				if (!tmpA && !tmpB) {
					return 0
				}
				return (valueA.diff(valueB)) * factor
			}
		},
		isSearchStringFoundForDatetimeDate(column, cell, searchString) {
			const date = new Moment(cell.value, 'YYYY-MM-DD').format('ll')
			if (date.includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForDatetimeDate(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
			const filterDate = new Moment(filterValue)
			const valueDate = new Moment(cell.value)

			if (filter.operator === 'is-equal' && filterDate.isSame(valueDate)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than' && filterDate.isBefore(valueDate)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than-or-equal' && filterDate.isSameOrBefore(valueDate)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than' && filterDate.isAfter(valueDate)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than-or-equal' && filterDate.isSameOrAfter(valueDate)) {
				cell.filterFound = true
				return true
			}
			return false
		},

	},
}
