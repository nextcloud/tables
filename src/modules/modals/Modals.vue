<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<CreateTable :show-modal="showModalCreateTable" @close="showModalCreateTable = false" />
		<DeleteTable :show-modal="tableToDelete !== null" :table="tableToDelete" @cancel="tableToDelete = null" />
		<EditTable :table-id="editTable" :show-modal="editTable !== null" @close="editTable = null" />
		<TransferTable :table="tableToTransfer" :show-modal="tableToTransfer !== null" @close="tableToTransfer = null" />

		<CreateColumn :show-modal="createColumnInfo !== null" :is-view="createColumnInfo?.isView" :element="createColumnInfo?.element" :preset="createColumnInfo?.preset" :is-custom-save="!!createColumnInfo?.onSave" @save="onSaveNewColumn" @close="createColumnInfo = null" />
		<EditColumn v-if="columnToEdit" :column="columnToEdit?.column" :is-view="columnToEdit.isView" :element-id="columnToEdit?.elementId" @close="columnToEdit = false" />
		<DeleteColumn v-if="columnToDelete" :is-view="columnToDelete?.isView" :element-id="columnToDelete?.elementId" :column-to-delete="columnToDelete?.column" @cancel="columnToDelete = null" />

		<CreateRow :columns="columnsForRow?.columns"
			:is-view="columnsForRow?.isView"
			:element-id="columnsForRow?.elementId"
			:show-modal="columnsForRow !== null"
			@close="columnsForRow = null" />
		<EditRow :columns="editRow?.columns"
			:row="editRow?.row"
			:is-view="editRow?.isView"
			:element="editRow?.element"
			:show-modal="editRow !== null"
			:out-transition="true"
			@close="editRow = null" />
		<DeleteRows v-if="rowsToDelete" :rows-to-delete="rowsToDelete?.rows" :is-view="rowsToDelete?.isView" :element-id="rowsToDelete?.elementId" @cancel="rowsToDelete = null" />

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

		<ImportScheme
			:show-modal="showImportScheme"
			:title="importSchemeTitle"
			@close="showImportScheme = false" />
		<CreateContext :show-modal="showModalCreateContext" @close="showModalCreateContext = false" />
		<EditContext :context-id="editContext" :show-modal="editContext !== null" @close="editContext = null" />
		<TransferContext :context="contextToTransfer" :show-modal="contextToTransfer !== null" @close="contextToTransfer = null" />
		<DeleteContext :show-modal="contextToDelete !== null" :context="contextToDelete" @cancel="contextToDelete = null" />
	</div>
</template>

<script>

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import CreateRow from './CreateRow.vue'
import ImportScheme from './ImportScheme.vue'
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
import EditTable from './EditTable.vue'
import EditContext from './EditContext.vue'
import TransferTable from './TransferTable.vue'
import CreateContext from './CreateContext.vue'
import TransferContext from './TransferContext.vue'
import DeleteContext from './DeleteContext.vue'

export default {
	components: {
		EditTable,
		DeleteView,
		CreateTable,
		Import,
		ImportScheme,
		DeleteRows,
		ViewSettings,
		EditRow,
		CreateColumn,
		EditColumn,
		DeleteColumn,
		CreateRow,
		DeleteTable,
		TransferTable,
		CreateContext,
		EditContext,
		TransferContext,
		DeleteContext,
	},

	data() {
		return {
			createColumnInfo: null,
			columnToEdit: null,
			columnToDelete: null,
			columnsForRow: null,
			editRow: null,
			rowsToDelete: null,
			viewToEdit: null,
			showModalCreateTable: false,
			showModalCreateContext: false,
			importToElement: null,
			showImportScheme: false,
			importSchemeTitle: '',
			createViewTableId: null, // if null, no modal open
			tableToDelete: null,
			viewToDelete: null,
			editTable: null,
			editContext: null,
			tableToTransfer: null,
			contextToTransfer: null,
			contextToDelete: null,
		}
	},

	mounted() {
		// table
		subscribe('tables:table:create', () => { this.showModalCreateTable = true })
		subscribe('tables:table:delete', table => { this.tableToDelete = table })
		subscribe('tables:table:edit', tableId => { this.editTable = tableId })
		subscribe('tables:table:transfer', table => { this.tableToTransfer = table })

		// views
		subscribe('tables:view:reload', () => { this.reload(true) })
		subscribe('tables:view:edit', view => { this.viewToEdit = { ...view, createView: false } })
		subscribe('tables:view:create', tableInfos => {
			this.viewToEdit = {
				view: { tableId: tableInfos.tableId, sort: [], filter: [] },
				viewSetting: tableInfos.viewSetting,
				createView: true,
			}
		})
		subscribe('tables:view:delete', view => { this.viewToDelete = view })

		// columns
		subscribe('tables:column:create', columnInfo => { this.createColumnInfo = columnInfo })
		subscribe('tables:column:edit', columnInfo => { this.columnToEdit = columnInfo })
		subscribe('tables:column:delete', columnInfo => { this.columnToDelete = columnInfo })

		// rows
		subscribe('tables:row:create', columnsInfo => { this.columnsForRow = columnsInfo })
		subscribe('tables:row:edit', rowInfo => { this.editRow = rowInfo })
		subscribe('tables:row:delete', tableInfo => {
			this.rowsToDelete = tableInfo
		})

		// misc
		subscribe('tables:modal:import', element => { this.importToElement = element })
		subscribe('tables:modal:scheme', title => { this.importSchemeTitle = title; this.showImportScheme = true })

		// context
		subscribe('tables:context:create', () => { this.showModalCreateContext = true })
		subscribe('tables:context:edit', contextId => { this.editContext = contextId })
		subscribe('tables:context:transfer', context => { this.contextToTransfer = context })
		subscribe('tables:context:delete', context => { this.contextToDelete = context })

	},
	unmounted() {
		unsubscribe('tables:view:reload', () => { this.reload(true) })
		unsubscribe('tables:column:create', columnInfo => { this.createColumnInfo = columnInfo })
		unsubscribe('tables:column:edit', columnInfo => { this.columnToEdit = columnInfo })
		unsubscribe('tables:column:delete', columnInfo => { this.columnToDelete = columnInfo })
		unsubscribe('tables:row:create', columnsInfo => { this.columnsForRow = columnsInfo })
		unsubscribe('tables:row:edit', rowInfo => { this.editRow = rowInfo })
		unsubscribe('tables:row:delete', tableInfo => {
			this.rowsToDelete = tableInfo
		})
		unsubscribe('tables:view:edit', view => { this.viewToEdit = { view, createView: false } })
		unsubscribe('tables:view:create', tableInfos => {
			this.viewToEdit = {
				view: { tableId: tableInfos.tableId, sort: [], filter: [] },
				viewSetting: tableInfos.viewSetting,
				createView: true,
			}
		})
		unsubscribe('tables:table:create', () => { this.showModalCreateTable = true })
		unsubscribe('tables:modal:import', element => { this.importToElement = element })
		unsubscribe('tables:table:delete', table => { this.tableToDelete = table })
		unsubscribe('tables:table:edit', tableId => { this.editTable = tableId })
		unsubscribe('tables:table:transfer', table => { this.tableToTransfer = table })
		unsubscribe('tables:context:create', () => { this.showModalCreateContext = true })
		unsubscribe('tables:context:edit', contextId => { this.editContext = contextId })
		unsubscribe('tables:context:transfer', context => { this.contextToTransfer = context })
		unsubscribe('tables:context:delete', context => { this.contextToDelete = context })
	},

	methods: {
		onSaveNewColumn(event) {
			if (this.createColumnInfo?.onSave) {
				this.createColumnInfo.onSave(event)
			}
		},
	},
}
</script>
