import moment from '@nextcloud/moment'

export default {

	methods: {

		downloadCsv(rows, columns, table) {
			if (!rows || rows.length === 0) {
				console.debug('downloadCSV has empty parameter, expected array ob row objects', rows)
			}

			const data = []
			rows.forEach(row => {
				const rowData = {}
				columns.forEach(column => {
					let set = null
					if (row.data) {
						set = row.data.find(d => d.columnId === column.id)
					}
					let value = ''
					console.debug('try to export csv', column)
					if (column.type === 'text' && column.subtype === 'long' && set) {
						value = set.value.replace(/(<([^>]+)>)/ig, '')
					} else {
						value = set ? set.value : ''
					}
					rowData[column.title] = value
				})
				data.push(rowData)
			})

			const csv = this.$papa.unparse(data)

			// remove smileys from title
			const tableTitle = table.title.replace(/([#0-9]\u20E3)|[\xA9\xAE\u203C\u2047-\u2049\u2122\u2139\u3030\u303D\u3297\u3299][\uFE00-\uFEFF]?|[\u2190-\u21FF][\uFE00-\uFEFF]?|[\u2300-\u23FF][\uFE00-\uFEFF]?|[\u2460-\u24FF][\uFE00-\uFEFF]?|[\u25A0-\u25FF][\uFE00-\uFEFF]?|[\u2600-\u27BF][\uFE00-\uFEFF]?|[\u2900-\u297F][\uFE00-\uFEFF]?|[\u2B00-\u2BF0][\uFE00-\uFEFF]?|(?:\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDEFF])[\uFE00-\uFEFF]?/g, '')
			this.$papa.download(csv, moment().format('YY-MM-DD_HH-mm') + '_' + tableTitle)
		},
	},
}
