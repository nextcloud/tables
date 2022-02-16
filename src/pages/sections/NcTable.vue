<template>
	<div>
		<div class="row padding-left" style="margin-bottom: 0px;">
			<div class="col-4">
				<Actions>
					<ActionButton :close-after-click="true" icon="icon-add" @click="newRow = true">
						{{ t('tables', 'Add new row') }}
					</ActionButton>
				</Actions>
				<Actions>
					<ActionCheckbox :checked.sync="showFilter">
						{{ t('tables', 'Show filter') }}
					</ActionCheckbox>
					<ActionButton :close-after-click="true" icon="icon-delete" @click="actionDeleteRows">
						{{ t('tables', 'Delete selected rows') }}
					</ActionButton>
					<ActionButton :close-after-click="true" icon="icon-download" @click="downloadCSV">
						{{ t('tables', 'Download CSV') }}
					</ActionButton>
					<ActionButton :close-after-click="true" icon="icon-external" @click="copyClipboard">
						{{ t('tables', 'Copy table to clipboard') }}
					</ActionButton>
					<!--
					<ActionButton :close-after-click="true" icon="icon-clippy">
						{{ t('tables', 'Click here and then »strg + v«') }}
					</ActionButton>
					-->
					<ActionButton :close-after-click="true" icon="icon-file" @click="print">
						{{ t('tables', 'Print') }}
					</ActionButton>
				</Actions>
			</div>
		</div>
		<div class="row">
			<TabulatorComponent ref="tabulator"
				v-model="getData"
				:options="getOptions"
				@cell-edited="actionEdited" />
		</div>
		<DialogConfirmation
			:show-modal="deleteRows"
			confirm-class="error"
			:title="n('tables', 'Delete selected row', 'Delete %n rows', deleteRowsCount, {})"
			:description="n('tables', 'Are you sure you want to delete the selected row?', 'Are you sure you want to delete the %n selected rows?', deleteRowsCount, {})"
			@confirm="deleteRowsAtBE"
			@cancel="deleteRows = false" />
		<CreateRow :columns="columns"
			:show-modal="newRow"
			@update-rows="actionUpdateRows"
			@close="newRow = false" />
	</div>
</template>

<script>
import { TabulatorComponent } from 'vue-tabulator'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import DialogConfirmation from '../../modals/DialogConfirmation'
import CreateRow from '../../modals/CreateRow'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
// import moment from '@nextcloud/moment'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'

export default {
	name: 'NcTable',
	components: {
		CreateRow,
		DialogConfirmation,
		TabulatorComponent,
		Actions,
		ActionButton,
		ActionCheckbox,
	},
	props: {
		columns: {
			type: Array,
			default: null,
		},
		rows: {
			type: Array,
			default: null,
		},
		options: {
			type: Object,
			default() {
				return {
					pagination: 'local',
					paginationSize: 10,
					paginationSizeSelector: [5, 10, 30, 100],
					layout: 'fitDataFill',
					clipboard: true,
					clipboardPasteAction: 'insert',
					printAsHtml: true,
				}
			},
		},
	},
	data() {
		return {
			newRow: false,
			deleteRows: false,
			deleteRowsCount: 0,
			showFilter: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		getColumnsDefinition() {
			const numberEditor = function(cell, onRendered, success, cancel, editorParams) {
				// cell - the cell component for the editable cell
				// onRendered - function to call when the editor has been rendered
				// success - function to call to pass the successfully updated value to Tabulator
				// cancel - function to call to abort the edit and return to a normal cell
				// editorParams - params object passed into the editorParams column definition property

				const editor = document.createElement('input')

				editor.setAttribute('type', 'number')
				editor.setAttribute('min', (editorParams.numberMin || editorParams.numberMin === 0) ? editorParams.numberMin : null)
				editor.setAttribute('max', (editorParams.numberMax || editorParams.numberMax === 0) ? editorParams.numberMax : null)

				editor.style.padding = '3px'
				editor.style.width = '100%'
				editor.style.boxSizing = 'border-box'

				// Set value of editor to the current value of the cell
				editor.value = (cell.getValue() === null || cell.getValue() === undefined) ? editorParams.default : cell.getValue()

				// set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
				onRendered(function() {
					editor.focus()
					editor.style.css = '100%'
				})

				// when the value has been set, trigger the cell to update
				/**
				 *
				 */
				function successFunc() {
					success(editor.value)
				}

				// editor.addEventListener('change', successFunc)
				editor.addEventListener('blur', successFunc)

				return editor
			}
			const minMaxFilterEditor = function(cell, onRendered, success, cancel, editorParams) {

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
			}
			const minMaxFilterFunction = function minMaxFilterFunction(headerValue, rowValue, rowData, filterParams) {
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
			}

			// start logic -------------------------------------

			const def = [
				{
					formatter: 'rowSelection',
					titleFormatter: 'rowSelection',
					align: 'center',
					headerSort: false,
					width: 60,
					print: false,
				},
			]
			if (this.columns) {
				this.columns.forEach(item => {
					let formatter = null
					let formatterParams = null
					let editorParams = null
					let customEditor = null
					let align = null
					let sorter = null
					let headerFilter = null
					let headerFilterFunc = null
					let headerFilterLiveFilter = null
					let validator = null

					// specific parameters depending on column type
					if (item.type === 'text' && item.subtype === 'long') {
						formatter = 'textarea'
						if (item.textMaxLength && parseInt(item.textMaxLength) !== -1) {
							validator = 'maxLength:' + item.textMaxLength
						} else {
							validator = item.mandatory ? 'required' : null
						}
					} else if (item.type === 'text' && item.subtype === 'line') {
						if (item.textMaxLength && parseInt(item.textMaxLength) !== -1) {
							validator = 'maxLength:' + item.textMaxLength
						} else {
							validator = item.mandatory ? 'required' : null
						}
					} else if (item.type === 'text' && item.subtype === 'link') {
						formatter = 'link'
						formatterParams = {
							// labelField:"name",
							// urlPrefix:"mailto://",
							target: '_blank',
						}
					} else if (item.type === 'number' && !item.subtype) {
						align = 'right'
						formatterParams = {
							suffix: item.numberSuffix,
							prefix: item.numberPrefix,
							precision: (item.numberDecimals !== undefined) ? item.numberDecimals : 2,
						}
						formatter = (cell, formatterParams, onRendered) => {
							// cell - the cell component
							// formatterParams - parameters set for the column
							// onRendered - function to call when the formatter has been rendered

							return (cell.getValue()) ? formatterParams.prefix + ' ' + (Math.round(cell.getValue() * 100) / 100).toFixed(formatterParams.precision) + ' ' + formatterParams.suffix : '' // return the contents of the cell;
						}
						editorParams = {
							default: item.numberDefault,
							numberMin: item.numberMin,
							numberMax: item.numberMax,
						}
						customEditor = numberEditor
						sorter = 'number'
						headerFilter = minMaxFilterEditor
						headerFilterFunc = minMaxFilterFunction
						headerFilterLiveFilter = false
						validator = item.mandatory ? 'required' : null
					} else if (item.type === 'number' && item.subtype === 'stars') {
						formatter = 'star'
						sorter = 'number'
						headerFilter = minMaxFilterEditor
						headerFilterFunc = minMaxFilterFunction
						headerFilterLiveFilter = false
						validator = item.mandatory ? 'required' : null
					} else if (item.type === 'number' && item.subtype === 'progress') {
						formatter = 'progress'
						formatterParams = {
							color: 'var(--color-primary-element-hover)',
						}
						sorter = 'number'
						headerFilter = minMaxFilterEditor
						headerFilterFunc = minMaxFilterFunction
						headerFilterLiveFilter = false
						validator = item.mandatory ? 'required' : null
					}

					// console.debug('item to push as column definition', item)
					def.push({
						title: item.title,
						field: 'column-' + item.id,
						formatter,
						formatterParams,
						headerFilter: this.showFilter ? headerFilter || 'input' : null,
						editor: (customEditor) || true,
						editorParams,
						align,
						minWidth: (item.type === 'number') ? 110 : 140,
						sorter,
						validator,
						headerFilterFunc,
						headerFilterLiveFilter,
					})
				})
			}
			// console.debug('columns definition array', def)
			return def
		},
		getOptions() {
			const lang = {
				specific: {
					pagination: {
						first: t('tables', 'First'),
						first_title: t('tables', 'First page'),
						last: t('tables', 'Last'),
						last_title: t('tables', 'Last page'),
						prev: t('tables', 'Back'),
						prev_title: t('tables', 'Previous page'),
						next: t('tables', 'Next'),
						next_title: t('tables', 'Next page'),
						page_size: t('tables', 'Number items'),
					},
					headerFilters: {
						default: t('tables', 'Textfilter'), // default header filter placeholder text
					},
				},
			}
			const o = Object.assign({}, this.options)
			o.columns = this.getColumnsDefinition
			o.locale = 'specific'
			o.langs = lang
			o.printHeader = '<h1>' + this.activeTable.title + '<h1>'
			return o
		},
		getData() {
			const d = []
			if (this.rows) {
				this.rows.forEach(item => {
					const t = { id: item.id }

					if (item.data) {
						item.data.forEach(c => {
							t['column-' + c.columnId] = c.value
						})
					}
					d.push(t)
				})
			}
			return d
		},
	},
	methods: {
		actionUpdateRows() {
			console.debug('NcTable action update rows -> emit action')
			this.$emit('update-rows')
		},
		actionDeleteRows() {
			const selectedRows = this.$refs.tabulator.getInstance().getSelectedRows()
			if (selectedRows && selectedRows.length > 0) {
				this.deleteRows = true
				this.deleteRowsCount = selectedRows.length
			} else {
				showInfo(t('tables', 'No selected rows to delete.'))
			}
		},
		async deleteRowsAtBE() {
			const selectedRows = this.$refs.tabulator.getInstance().getSelectedRows()
			if (selectedRows && selectedRows.length > 0) {
				let error = false
				for (const row of selectedRows) {
					console.debug('try to delete row with id', row._row.data.id)
					try {
						const res = await axios.delete(generateUrl('/apps/tables/row/' + row._row.data.id))
						console.debug('successfully deleted row', res)
					} catch (e) {
						console.error(e)
						showError(t('tables', 'Could not delete row.'))
						error = true
					}
				}
				if (!error) {
					showSuccess(n('tables', 'Selected row was deleted.', 'Selected %n rows were deleted.', this.deleteRowsCount, {}))
				}
				this.$emit('update-rows')
			} else {
				showInfo(t('tables', 'No selected rows to delete.'))
			}
			this.deleteRows = false
			this.deleteRowsCount = 0
		},
		async actionEdited(data) {
			const newValue = '' + data._cell.value
			const rowId = parseInt(data._cell.row.data.id)
			const column = data._cell.column.field
			const columnId = parseInt(column.split('-')[1])
			console.debug('data edited', data)
			try {
				console.debug('try to send cell', { rowId, newValue, columnId })

				// if row exists
				if (rowId) {
					await axios.put(generateUrl('/apps/tables/row/' + rowId + '/column/' + columnId), { tableId: this.activeTable.id, data: newValue })
				} else {
					// else create new row in BE
					const res = await axios.post(generateUrl('/apps/tables/row/column/' + columnId), { tableId: this.activeTable.id, data: newValue })
					console.debug('new row after creation', res)
					this.$emit('update-rows')
				}
				showSuccess(t('tables', 'New value successfully saved.'))
				this.newRow = false
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not save new value.'))
			}
		},
		downloadCSV() {
			// remove icons from title for download-filename
			const title = this.activeTable.title.replace(/([#0-9]\u20E3)|[\xA9\xAE\u203C\u2047-\u2049\u2122\u2139\u3030\u303D\u3297\u3299][\uFE00-\uFEFF]?|[\u2190-\u21FF][\uFE00-\uFEFF]?|[\u2300-\u23FF][\uFE00-\uFEFF]?|[\u2460-\u24FF][\uFE00-\uFEFF]?|[\u25A0-\u25FF][\uFE00-\uFEFF]?|[\u2600-\u27BF][\uFE00-\uFEFF]?|[\u2900-\u297F][\uFE00-\uFEFF]?|[\u2B00-\u2BF0][\uFE00-\uFEFF]?|(?:\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDEFF])[\uFE00-\uFEFF]?/g, '')
			this.$refs.tabulator.getInstance().download('csv', (title) || 'download')
		},
		copyClipboard() {
			console.debug('tab instance', this.$refs.tabulator.getInstance())
			this.$refs.tabulator.getInstance().copyToClipboard('all')
			showSuccess(t('tables', 'Table copied to clipboard.'))
		},
		importClipboard() {
			document.getElementById('tabulator').focus()
			showInfo(t('tables', 'Now press »strg + v«'))
		},
		print() {
			this.$refs.tabulator.getInstance().print('all', true, {})
		},
	},
}
</script>
