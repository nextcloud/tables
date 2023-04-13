export default {

	methods: {
		getValueStringForTextLong(valueObject) {
			return valueObject.value.replace(/(<([^>]+)>)/ig, '')
		},
	},
}
