<template>
	<div>
		<CreateTable :show-modal="showModalCreateTable" @close="showModalCreateTable = false" />
		<DeleteTable :show-modal="tableToDelete !== null" :table="tableToDelete" @cancel="tableToDelete = null" />

		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
		<EditColumn v-if="columnToEdit" :column="columnToEdit" @close="columnToEdit = false" />
		<DeleteColumn v-if="columnToDelete" :column-to-delete="columnToDelete" @cancel="columnToDelete = null" />

		<CreateRow :columns="columnsForRow"
			:show-modal="columnsForRow !== null"
			@close="columnsForRow = null" />
		<EditRow :columns="editRow?.columns"
			:row="editRow?.row"
			:show-modal="editRow !== null"
			:out-transition="true"
			@close="editRow = null" />
		<DeleteRows v-if="rowsToDelete" :rows-to-delete="rowsToDelete" @cancel="rowsToDelete = null" />

		<ViewSettings
			:show-modal="viewToEdit !== null"
			:view="viewToEdit?.view"
			:create-view="viewToEdit?.createView"
			:view-setting="viewToEdit?.viewSetting"
			@close="viewToEdit = null" />
		<DeleteView :show-modal="viewToDelete !== null" :view="viewToDelete" @cancel="viewToDelete = null" />

		<Import
			:show-modal="importToElement !== null"
			:element="importToElement?.element"
			:is-element-view="importToElement?.isView"
			@close="importToElement = null" />
	</div>
</template>

<script>

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import CreateRow from './CreateRow.vue'
import DeleteColumn from './DeleteColumn.vue'
import EditColumn from './EditColumn.vue'
import CreateColumn from './CreateColumn.vue'
import EditRow from './EditRow.vue'
import ViewSettings from './ViewSettings.vue'
import DeleteRows from './DeleteRows.vue'
import Import from './Import.vue'
import DeleteTable from './DeleteTable.vue'
import CreateTable from './CreateTable.vue'
import DeleteView from './DeleteView.vue'

export default {
	components: {
		DeleteView,
		CreateTable,
		Import,
		DeleteRows,
		ViewSettings,
		EditRow,
		CreateColumn,
		EditColumn,
		DeleteColumn,
		CreateRow,
		DeleteTable,
	},

	data() {
		return {
			showCreateColumn: false,
			columnToEdit: null,
			columnToDelete: null,
			columnsForRow: null,
			editRow: null,
			rowsToDelete: null,
			viewToEdit: null,
			showModalCreateTable: false,
			importToElement: null,
			createViewTableId: null, // if null, no modal open
			tableToDelete: null,
			viewToDelete: null,
		}
	},

	mounted() {
		// table
		subscribe('tables:table:create', () => { this.showModalCreateTable = true })
		subscribe('tables:table:delete', table => { this.tableToDelete = table })

		// views
		subscribe('tables:view:reload', () => { this.reload(true) })
		subscribe('tables:view:edit', view => { this.viewToEdit = { ...view, createView: false } })
		subscribe('tables:view:create', tableId => {
			this.viewToEdit = {
				view: { tableId, sort: [], filter: [] },
				createView: true,
			}
		})
		subscribe('tables:view:delete', view => { this.viewToDelete = view })

		// columns
		subscribe('tables:column:create', () => { this.showCreateColumn = true })
		subscribe('tables:column:edit', column => { this.columnToEdit = column })
		subscribe('tables:column:delete', column => { this.columnToDelete = column })

		// rows
		subscribe('tables:row:create', columns => { this.columnsForRow = columns })
		subscribe('tables:row:edit', row => { this.editRow = row })
		subscribe('tables:row:delete', rows => { this.rowsToDelete = rows })

		// misc
		subscribe('tables:modal:import', element => { this.importToElement = element })
	},
	unmounted() {
		unsubscribe('tables:view:reload', () => { this.reload(true) })
		unsubscribe('tables:column:create', () => { this.showCreateColumn = true })
		unsubscribe('tables:column:edit', column => { this.columnToEdit = column })
		unsubscribe('tables:column:delete', column => { this.columnToDelete = column })
		unsubscribe('tables:row:create', columns => { this.columnsForRow = columns })
		unsubscribe('tables:row:edit', row => { this.editRow = row })
		unsubscribe('tables:row:delete', rows => { this.rowsToDelete = rows })
		unsubscribe('tables:view:edit', view => { this.viewToEdit = { view, createView: false } })
		unsubscribe('tables:view:create', tableId => {
			this.viewToEdit = {
				view: { tableId, sort: [], filter: [] },
				createView: true,
			}
		})
		unsubscribe('tables:table:create', () => { this.showModalCreateTable = true })
		unsubscribe('tables:modal:import', element => { this.importToElement = element })
		unsubscribe('tables:table:delete', table => { this.tableToDelete = table })
	},
}
</script>
