export default {

	methods: {
		getValueStringForSelection(valueObject, column) {
			column = column || this.column || null
			valueObject = valueObject || this.value || null

			return this.getLabelForSelection(valueObject.value, column)
		},

		getLabelForSelection(id, column) {
			column = column || this.column
			id = id || this.value
			const i = column?.selectionOptions?.findIndex((obj) => obj.id === id)
			return column?.selectionOptions[i]?.label
		},

		isDeletedLabelForSelection(selectionOptions, value) {
			selectionOptions = selectionOptions || this.column?.selectionOptions
			value = value || this.value
			const i = selectionOptions?.findIndex((obj) => obj.id === value)
			return !!selectionOptions[i]?.deleted
		},

		sortingSelection(column, mode) {
			const factor = mode === 'desc' ? -1 : 1
			return function(rowA, rowB) {
				const selectionIdA = rowA.data.find(item => item.columnId === column.id)?.value || null
				const valueA = selectionIdA !== null ? column.selectionOptions.find(item => item.id === selectionIdA)?.label : ''
				const selectionIdB = rowB.data.find(item => item.columnId === column.id)?.value || null
				const valueB = selectionIdB !== null ? column.selectionOptions.find(item => item.id === selectionIdB)?.label : ''
				return ((valueA < valueB) ? -1 : (valueA > valueB) ? 1 : 0) * factor
			}
		},
		isSearchStringFoundForSelection(column, cell, searchString) {
			if (cell.value !== null && (this.getLabelForSelection(cell.value, column))?.toLowerCase().includes(searchString)) {
				cell.searchStringFound = true
				return true
			}
			return false
		},
		isFilterFoundForSelection(column, cell, filter) {
			const filterValue = filter.magicValuesEnriched ? filter.magicValuesEnriched : filter.value

			if (filter.operator === 'contains' && (this.getLabelForSelection(cell.value, column))?.includes(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'begins-with' && (this.getLabelForSelection(cell.value, column))?.startsWith(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'ends-with' && (this.getLabelForSelection(cell.value, column))?.endsWith(filterValue)) {
				cell.filterFound = true
				return true
			}
			if (filter.operator === 'is-equal' && this.getLabelForSelection(cell.value, column) === filterValue) {
				cell.filterFound = true
				return true
			}
			return false
		},

	},

}
