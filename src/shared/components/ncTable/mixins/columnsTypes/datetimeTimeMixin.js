import Moment from '@nextcloud/moment'

export default {

	methods: {
		sortingDatetimeTime(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const tmpA = rowA.data.find(item => item.columnId === column.id)?.value || ''
				const valueA = new Moment(tmpA, 'HH:mm')
				const tmpB = rowB.data.find(item => item.columnId === column.id)?.value || ''
				const valueB = new Moment(tmpB, 'HH:mm')
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
		isSearchStringFoundForDatetimeTime(column, cell, searchString) {
			const time = new Moment(cell.value, 'HH:mm').format('LT')
			if (time.toLowerCase().includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForDatetimeTime(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value
			const filterTime = new Moment(filterValue, 'HH:mm')
			const valueTime = new Moment(cell.value, 'HH:mm')

			if (filter.operator === 'is-equal' && filterTime.isSame(valueTime)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than' && filterTime.isBefore(valueTime)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-greater-than-or-equal' && filterTime.isSameOrBefore(valueTime)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than' && filterTime.isAfter(valueTime)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-lower-than-or-equal' && filterTime.isSameOrAfter(valueTime)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-empty' && !valueTime) {
				cell.filterFound = true
				return true
			}
			return false
		},

	},
}
