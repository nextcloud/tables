export default {

	methods: {
		ucfirst(str) {
			if (!str) {
				return ''
			}
			// converting first letter to uppercase
			return str.charAt(0).toUpperCase() + str.slice(1)
		},
	},

}
