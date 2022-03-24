import { mapGetters } from 'vuex'

export default {
	components: {
		...mapGetters(['activeTable']),
	},
	methods: {
		numberStarsFormatterClipboard(cell, formatterParams, onRendered) {
			const starEmpty = '☆'
			const starFull = '★'
			const v = parseInt(cell.getValue())
			let res = starEmpty + starEmpty + starEmpty + starEmpty + starEmpty
			if (v && v === 1) {
				res = starFull + starEmpty + starEmpty + starEmpty + starEmpty
			} else if (v && v === 2) {
				res = starFull + starFull + starEmpty + starEmpty + starEmpty
			} else if (v && v === 3) {
				res = starFull + starFull + starFull + starEmpty + starEmpty
			} else if (v && v === 4) {
				res = starFull + starFull + starFull + starFull + starEmpty
			} else if (v && v === 5) {
				res = starFull + starFull + starFull + starFull + starFull
			}
			return res
		},
		selectionCheckFormatterClipboard(cell, formatterParams, onRendered) {
			return cell.getValue() === true ? 1 : 0
		},
		selectionCheckAccessor(value, data, type, params, column) {
			return parseInt(value) === 1 || value === true
		},
		numberStarsAccessor(value, data, type, params, column) {
			const starFull = '★'
			console.debug('try to mutate paste stars', value)
			console.debug('mutate res', value.split(starFull).length - 1)
			return value.split(starFull).length - 1
		},
	},
}
