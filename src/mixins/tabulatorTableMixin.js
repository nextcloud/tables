import { mapGetters } from 'vuex'
import Moment from '@nextcloud/moment'

export default {
	components: {
		...mapGetters(['activeTable']),
	},
	methods: {
		minMaxFilterEditor(cell, onRendered, success, cancel, editorParams) {
			let end = null
			const container = document.createElement('span')

			// create and style inputs
			const start = document.createElement('input')
			start.setAttribute('type', 'number')
			start.setAttribute('placeholder', 'Min')
			start.setAttribute('min', 0)
			start.setAttribute('max', 100)
			start.style.padding = '4px'
			start.style.width = '50%'
			start.style.boxSizing = 'border-box'

			start.value = cell.getValue()

			/**
			 *
			 */
			function buildValues() {
				success({
					start: start.value,
					end: end.value,
				})
			}

			// noinspection JSClosureCompilerSyntax
			/**
			 * @param {number} e - event
			 */
			function keypress(e) {
				if (e.keyCode === 13) {
					buildValues()
				}

				if (e.keyCode === 27) {
					cancel()
				}
			}

			end = start.cloneNode()
			end.setAttribute('placeholder', 'Max')

			start.addEventListener('change', buildValues)
			start.addEventListener('blur', buildValues)
			start.addEventListener('keydown', keypress)

			end.addEventListener('change', buildValues)
			end.addEventListener('blur', buildValues)
			end.addEventListener('keydown', keypress)

			container.appendChild(start)
			container.appendChild(end)

			return container
		},
		minMaxFilterFunction(headerValue, rowValue, rowData, filterParams) {
			// headerValue - the value of the header filter element
			// rowValue - the value of the column in this row
			// rowData - the data for the row being filtered
			// filterParams - params object passed to the headerFilterFuncParams property

			if (rowValue) {
				if (headerValue.start !== '') {
					if (headerValue.end !== '') {
						return rowValue >= headerValue.start && rowValue <= headerValue.end
					} else {
						return rowValue >= headerValue.start
					}
				} else {
					if (headerValue.end !== '') {
						return rowValue <= headerValue.end
					}
				}
			}

			return true // must return a boolean, true if it passes the filter.
		},
		headerFilterFunctionCheckbox(headerValue, rowValue, rowData, filterParams) {
			// headerValue - the value of the header filter element
			// rowValue - the value of the column in this row
			// rowData - the data for the row being filtered
			// filterParams - params object passed to the headerFilterFuncParams property

			// only filter if filter checkbox is true
			return ('' + headerValue === 'true' && '' + rowValue === 'true') || '' + headerValue === 'false' // must return a boolean, true if it passes the filter.
		},
		numberFormatter(cell, formatterParams, onRendered) {
			// cell - the cell component
			// formatterParams - parameters set for the column
			// onRendered - function to call when the formatter has been rendered

			if (cell.getValue()) {
				return formatterParams.prefix + ' ' + (Math.round(cell.getValue() * 100) / 100).toFixed(formatterParams.precision) + ' ' + formatterParams.suffix
			} else {
				return ''
			}
		},
		datetimeFormatter(cell, formatterParams, onRendered) {
			return (cell.getValue()) ? Moment(cell.getValue(), 'YYYY-MM-DD HH:mm:ss').format('lll') : ''
		},
		datetimeDateFormatter(cell, formatterParams, onRendered) {
			return (cell.getValue()) ? Moment(cell.getValue(), 'YYYY-MM-DD HH:mm:ss').format('ll') : ''
		},
		datetimeTimeFormatter(cell, formatterParams, onRendered) {
			return (cell.getValue()) ? Moment(cell.getValue(), 'HH:mm:ss').format('LT') : ''
		},
		getRowSelectionColumnDef() {
			return {
				formatter: 'rowSelection',
				titleFormatter: 'rowSelection',
				hozAlign: 'center',
				headerSort: false,
				width: 60,
				print: false,
				clipboard: false,
			}
		},
		getRowEditColumnDef() {
			return {
				width: 60,
				hozAlign: 'center',
				headerSort: false,
				field: 'editRow',
				print: false,
				clipboard: false,
				formatter: (cell, formatterParams, onRendered) => {
					return '<button class="icon-rename" />'
				},
			}
		},
	},
}
