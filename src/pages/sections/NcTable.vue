<template>
	<div>
		<TabulatorComponent ref="tabulator"
			v-model="data"
			:options="options2"
			@cell-click="updateSelectedRows"
			@cell-edited="edited" />
		<button @click="newRow = true">
			{{ t('tables', 'Add new row') }}
		</button>
		<button class="error" @click="deleteRows = true">
			{{ n('tables', 'Delete selected row', 'Delete %n rows', selectedRowIds.length, {}) }}
		</button>
		<button @click="debug">
			debug tab
		</button>
		<DialogConfirmation
			:show-modal="deleteRows"
			:title="n('tables', 'Delete selected row', 'Delete %n rows', selectedRowIds.length, {})"
			:description="n('tables', 'Are you sure you want to delete the selected row?', 'Are you sure you want to delete the %n selected rows?', selectedRowIds.length, {})"
			@confirm="deleteRow"
			@cancel="deleteRows = false" />
	</div>
</template>

<script>
import { TabulatorComponent } from 'vue-tabulator'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import DialogConfirmation from '../../modals/DialogConfirmation'

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
	},
	data() {
		return {
			options: {
				resizableColumns: 'header',
				columns: this.columnsDefinition,
				// footerElement: '<button>TEST</button>',
				// initialSort: [
				// { column: 'age', dir: 'desc' }, // sort by this first
				// ],
				layout: 'fitDataFill',
			},
			newRow: false,
			deleteRows: false,
			selectedRowIds: [],
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		columnsDefinition() {
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
					if (item.type === 'text' && item.textMultiline) {
						formatter = 'textarea'
					}
					def.push({
						title: item.title,
						field: 'column-' + item.id,
						editor: true,
						formatter,
					})
				})
			}
			console.debug('columns definition array', def)
			return def
		},
		options2() {
			return {
				resizableColumns: 'header',
				columns: this.columnsDefinition,
				// footerElement: '<button>TEST</button>',
				// initialSort: [
				// { column: 'age', dir: 'desc' }, // sort by this first
				// ],
				layout: 'fitDataFill',
			}
		},
		data() {
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
		debug() {
			console.debug('tabulator debug', this.$refs.tabulator.getInstance().getSelectedRows())
		},
		updateSelectedRows() {
			console.debug('update selected rows')
			const rows = this.$refs.tabulator.getInstance().getSelectedRows()
			const selectedRowIds = []
			rows.forEach(row => {
				selectedRowIds.push(row._row.data.id)
			})
		},
		deleteRow() {
			this.deleteRows = false
		},
		async edited(data) {
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
<style scoped>

	[class^='icon-']:hover, .icon-delete:hover {
		cursor: pointer;
	}

</style>
