import Moment from '@nextcloud/moment'

export default {

	methods: {
		sortingDatetime(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const tmpA = rowA.data.find(item => item.columnId === column.id)?.value || ''
				const valueA = new Moment(tmpA, 'YYY-MM-DD HH:mm')
				const tmpB = rowB.data.find(item => item.columnId === column.id)?.value || ''
				const valueB = new Moment(tmpB, 'YYY-MM-DD HH:mm')
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
		isSearchStringFoundForDatetime(column, cell, searchString) {
			const date = new Moment(cell.value, 'YYYY-MM-DD HH:mm').format('lll')
			if (date.includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForDatetime(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
			const filterDate = new Moment(filterValue, 'YYY-MM-DD HH:mm')
			const valueDate = new Moment(cell.value, 'YYY-MM-DD HH:mm')

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
