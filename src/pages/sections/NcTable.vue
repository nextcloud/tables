<template>
	<div>
		<div class="row space-LR space-T">
			<div v-if="hasColumns" class="col-4" style="display: flex;">
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
					<ActionButton :close-after-click="true" icon="icon-clippy" @click="importClipboard">
						{{ t('tables', 'Paste from clipboard') }}
					</ActionButton>
					<ActionButton :close-after-click="true" icon="icon-file" @click="print">
						{{ t('tables', 'Print') }}
					</ActionButton>
				</Actions>
				<div v-if="insertedRows" class="insertedRowsInfo">
					Inserted rows: {{ insertedRows }}
				</div>
			</div>
		</div>
		<div v-if="hasColumns" class="row">
			<TabulatorComponent ref="tabulator"
				v-model="getData"
				:options="getOptions"
				@cell-edited="actionEdited"
				@cell-click="actionCellClick"
				@row-added="callbackRowAdded" />
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
		<EditRow :columns="columns"
			:row="getEditRow"
			:show-modal="getEditRow !== null"
			@update-rows="actionUpdateRows"
			@close="editRowId = null" />
		<PasteRowsInfo :show-modal="showModalPasteRowsInfo" @close="showModalPasteRowsInfo = false" />
	</div>
</template>

<script>
import { TabulatorComponent } from 'vue-tabulator'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showInfo, showSuccess, showWarning } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import DialogConfirmation from '../../modals/DialogConfirmation'
import CreateRow from '../../modals/CreateRow'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import EditRow from '../../modals/EditRow'
import PasteRowsInfo from '../../modals/PasteRowsInfo'
import tabulatorTableMixin from '../../mixins/tabulatorTableMixin'
import tabulatorPrintMixin from '../../mixins/tabulatorPrintMixin'
import tabulatorClipboardMixin from '../../mixins/tabulatorClipboardMixin'

export default {
	name: 'NcTable',
	components: {
		PasteRowsInfo,
		EditRow,
		CreateRow,
		DialogConfirmation,
		TabulatorComponent,
		Actions,
		ActionButton,
		ActionCheckbox,
	},
	mixins: [tabulatorPrintMixin, tabulatorTableMixin, tabulatorClipboardMixin],
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
			editRowId: null,
			insertedRows: null,
			insertedRowsTimer: null,
			showModalPasteRowsInfo: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		getColumnsDefinition() {
			const def = [
				this.getRowSelectionColumnDef(),
				this.getRowEditColumnDef(),
			]
			if (this.columns) {
				this.columns.forEach(item => {
					let formatter = null
					let formatterParams = null
					let hozAlign = null
					let sorter = null
					let headerFilter = null
					let headerFilterFunc = null
					let headerFilterLiveFilter = null
					let validator = null
					let minWidth = 140
					let width = null
					let formatterClipboard = false
					let mutatorClipboard = null

					// specific parameters depending on column type
					if (item.type === 'text' && item.subtype === 'long') {
						formatter = 'html'
						if (item.textMaxLength && parseInt(item.textMaxLength) !== -1) {
							validator = 'maxLength:' + item.textMaxLength
						} else {
							validator = item.mandatory ? 'required' : null
						}
						width = 300
					} else if (item.type === 'text' && item.subtype === 'line') {
						if (item.textMaxLength && parseInt(item.textMaxLength) !== -1) {
							validator = 'maxLength:' + item.textMaxLength
						} else {
							validator = item.mandatory ? 'required' : null
						}
					} else if (item.type === 'text' && item.subtype === 'link') {
						formatter = 'link'
						formatterParams = {
							target: '_blank',
						}
					} else if (item.type === 'number' && !item.subtype) {
						hozAlign = 'right'
						formatterParams = {
							suffix: item.numberSuffix,
							prefix: item.numberPrefix,
							precision: (item.numberDecimals !== undefined) ? item.numberDecimals : 2,
						}
						formatter = this.numberFormatter
						sorter = 'number'
						headerFilter = this.minMaxFilterEditor
						headerFilterFunc = this.minMaxFilterFunction
						headerFilterLiveFilter = false
						validator = item.mandatory ? 'required' : null
						minWidth = 110
					} else if (item.type === 'number' && item.subtype === 'stars') {
						formatter = 'star'
						sorter = 'number'
						headerFilter = this.minMaxFilterEditor
						headerFilterFunc = this.minMaxFilterFunction
						headerFilterLiveFilter = false
						validator = item.mandatory ? 'required' : null
						minWidth = 110
						formatterClipboard = this.numberStarsFormatterClipboard
						mutatorClipboard = this.numberStarsAccessor
					} else if (item.type === 'number' && item.subtype === 'progress') {
						formatter = 'progress'
						formatterParams = {
							color: 'var(--color-placeholder-dark)',
						}
						sorter = 'number'
						headerFilter = this.minMaxFilterEditor
						headerFilterFunc = this.minMaxFilterFunction
						headerFilterLiveFilter = false
						validator = item.mandatory ? 'required' : null
					} else if (item.type === 'selection' && item.subtype === 'check') {
						formatter = 'tickCross'
						validator = item.mandatory ? 'required' : null
						minWidth = 80
						headerFilterFunc = this.headerFilterFunctionCheckbox
						headerFilter = true
						formatterClipboard = this.selectionCheckFormatterClipboard
						mutatorClipboard = this.selectionCheckAccessor
					} else if (item.type === 'datetime' && !item.subtype) {
						formatter = this.datetimeFormatter
						validator = item.mandatory ? 'required' : null
						minWidth = 100
					} else if (item.type === 'datetime' && item.subtype === 'date') {
						formatter = this.datetimeDateFormatter
						validator = item.mandatory ? 'required' : null
						minWidth = 100
					} else if (item.type === 'datetime' && item.subtype === 'time') {
						formatter = this.datetimeTimeFormatter
						validator = item.mandatory ? 'required' : null
						minWidth = 100
					}

					// console.debug('item to push as column definition', item)
					def.push({
						title: item.title,
						field: 'column-' + item.id,
						formatter,
						formatterParams,
						headerFilter: this.showFilter ? headerFilter || 'input' : null,
						editor: false,
						hozAlign,
						minWidth,
						width,
						sorter,
						validator,
						headerFilterFunc,
						headerFilterLiveFilter,
						formatterClipboard,
						mutatorClipboard,
					})
				})
			}
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
						prev: t('tables', 'Previous'),
						prev_title: t('tables', 'Previous page'),
						next: t('tables', 'Next'),
						next_title: t('tables', 'Next page'),
						page_size: t('tables', 'Number items'),
					},
					headerFilters: {
						default: t('tables', 'Text filter'), // default header filter placeholder text
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
		getEditRow() {
			if (this.editRowId !== null) {
				return this.rows.filter(item => {
					return item.id === this.editRowId
				})[0]
			} else {
				return null
			}
		},
		hasColumns() {
			return !(this.columns === null || this.columns.length === 0)
		},
	},
	methods: {
		getColumnSetup() {
			// http://tabulator.info/docs/4.4/persist
			const tabulatorInstance = this.$refs.tabulator.getInstance()
			console.debug('column setup', tabulatorInstance.getColumnLayout())
		},
		async callbackRowAdded(row) {
			// this is triggered if a user paste data from clipboard
			const data = []
			for (const [key, value] of Object.entries(row._row.data)) {
				const parts = key.split('-')
				if (parts[0] === 'column') {
					data.push({
						columnId: parseInt(parts[1]),
						value,
					})
				}
			}

			try {
				const res = await axios.post(generateUrl('/apps/tables/row'), { tableId: this.activeTable.id, data })
				if (res.status === 200) {
					if (!this.insertedRows) {
						this.insertedRows = 1
					} else {
						this.insertedRows++
					}
					clearTimeout(this.insertedRowsTimer)
					this.insertedRowsTimer = setTimeout(() => {
						showSuccess(n('tables', '%n row was saved.', '%n rows were saved.', this.insertedRows))
						this.insertedRows = null
						this.actionUpdateRows()
					}, 2000)
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new row'))
			}
		},
		actionUpdateRows() {
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
					try {
						const res = await axios.delete(generateUrl('/apps/tables/row/' + row._row.data.id))
						if (res.status !== 200) {
							error = true
							console.debug('axios error', res)
						}
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
			try {
				// if row exists
				if (rowId) {
					const res = await axios.put(generateUrl('/apps/tables/row/' + rowId + '/column/' + columnId), { tableId: this.activeTable.id, data: newValue })
					if (res.status === 200) {
						showSuccess(t('tables', 'New value successfully saved.'))
					} else {
						showWarning(t('tables', 'Sorry, something went wrong.'))
						console.debug('axios error', res)
					}
				} else {
					// else create new row in BE
					const res = await axios.post(generateUrl('/apps/tables/row/column/' + columnId), { tableId: this.activeTable.id, data: newValue })
					if (res.status === 200) {
						showSuccess(t('tables', 'New value successfully saved.'))
					} else {
						showWarning(t('tables', 'Sorry, something went wrong.'))
						console.debug('axios error', res)
					}
					this.$emit('update-rows')
				}
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
			this.$refs.tabulator.getInstance().copyToClipboard('all')
			showSuccess(t('tables', 'Table copied to clipboard.'))
		},
		importClipboard() {
			this.showModalPasteRowsInfo = true
		},
		actionCellClick(e, cell) {
			// e - the click event object
			// cell - cell component
			if (cell._cell.column.field === 'editRow') {
				// console.debug('I have to edit row with id: ', cell._cell.row.data.id)
				this.editRowId = cell._cell.row.data.id
			}
		},
	},
}
</script>
<style scoped>

.insertedRowsInfo {
	align-self: center;
	padding-left: 10px;
}

</style>
