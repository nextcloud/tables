<template>
	<div>
		<div class="row padding-left">
			<div class="col-4">
				<button class="icon-delete" @click="actionDeleteRows" />
				<button class="icon-add" @click="newRow = true" />
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
	</div>
</template>

<script>
import { TabulatorComponent } from 'vue-tabulator'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import DialogConfirmation from '../../modals/DialogConfirmation'
// import moment from '@nextcloud/moment'

export default {
	name: 'NcTable',
	components: {
		DialogConfirmation,
		TabulatorComponent,
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
					paginationSize: 30,
					paginationSizeSelector: [5, 10, 30, 100],
					layout: 'fitColumns',
				}
			},
		},
	},
	data() {
		return {
			newRow: false,
			deleteRows: false,
			deleteRowsCount: 0,
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

				// create and style editor
				const editor = document.createElement('input')

				editor.setAttribute('type', 'number')

				// create and style input
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

				// return the editor element
				return editor
			}

			const def = [
				{
					formatter: 'rowSelection',
					titleFormatter: 'rowSelection',
					align: 'center',
					headerSort: false,
					width: 60,
				},
			]
			if (this.columns) {
				this.columns.forEach(item => {
					let formatter = null
					let formatterParams = null
					let editorParams = null
					let customEditor = null
					let align = null
					if (item.type === 'text' && item.textMultiline) {
						formatter = 'textarea'
					} else if (item.type === 'number') {
						align = 'right'
						formatter = 'money'
						formatterParams = {
							suffix: item.suffix,
							prefix: item.prefix,
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
						}
						customEditor = numberEditor
					}
					def.push({
						title: item.title,
						field: 'column-' + item.id,
						// editor: true,
						formatter,
						formatterParams,
						headerFilter: 'input',
						editor: (customEditor) || true,
						editorParams,
						align,
					})
				})
			}
			console.debug('columns definition array', def)
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
			// o.responsiveLayout = 'collapse'
			// o.columnMinWidth = 80
			return o
		},
		getData() {
			const d = []
			if (this.newRow) {
				d.push({})
			}
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
			const newValue = data._cell.value
			const rowId = data._cell.row.data.id
			const column = data._cell.column.field
			const columnId = column.split('-')[1]
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
	},
}
</script>
