import Moment from '@nextcloud/moment'

export default {
	filters: {
		niceDateTime(value, format) {
			if (!format) {
				format = 'lll'
			}
			return (value) ? Moment(value, 'YYYY-MM-DD HH:mm:ss').format(format) : ''
		},
		niceDate(value, format) {
			if (!format) {
				format = 'll'
			}
			return (value) ? Moment(value, 'YYYY-MM-DD').format(format) : ''
		},
		niceTime(value, format) {
			if (!format) {
				format = 'LT'
			}
			return (value) ? Moment(value, 'HH:mm:ss').format(format) : ''
		},
	},
}
