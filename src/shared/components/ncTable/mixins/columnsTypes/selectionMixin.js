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
			return column?.selectionOptions[i]?.label || null
		},

		isDeletedLabelForSelection(selectionOptions, value) {
			selectionOptions = selectionOptions || this.column?.selectionOptions
			value = value || this.value
			const i = selectionOptions?.findIndex((obj) => obj.id === value)
			return !!selectionOptions[i]?.deleted
		},
	},

}
