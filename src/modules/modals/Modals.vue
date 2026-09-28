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
			:prefill-data="columnsForRow?.prefillData ?? null"
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
		<ImportTableScheme
			:show-modal="importSchemaToTable !== null"
			:table="importSchemaToTable"
			@close="importSchemaToTable = null" />
		<CreateContext :show-modal="showModalCreateContext" @close="showModalCreateContext = false" />
		<EditContext :context-id="editContext" :show-modal="editContext !== null" @close="editContext = null" />
		<ImportContextScheme :context-id="importContext" :show-modal="importContext !== null" @close="importContext = null" />
		<TransferContext :context="contextToTransfer" :show-modal="contextToTransfer !== null" @close="contextToTransfer = null" />
		<DeleteContext :show-modal="contextToDelete !== null" :context="contextToDelete" @cancel="contextToDelete = null" />
	</div>
</template>

<script>

import { useEventBusSubscriptions } from '../../shared/composables/useEventBusSubscriptions.js'
import CreateRow from './CreateRow.vue'
import ImportScheme from './ImportScheme.vue'
import ImportTableScheme from './ImportTableScheme.vue'
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
import ImportContextScheme from './ImportContextScheme.vue';

export default {
	components: {
		EditTable,
		DeleteView,
		CreateTable,
		Import,
		ImportScheme,
		ImportTableScheme,
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
		ImportContextScheme,
	},

	setup() {
		return { ...useEventBusSubscriptions() }
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
			importSchemaToTable: null,
			showImportScheme: false,
			importSchemeTitle: '',
			createViewTableId: null, // if null, no modal open
			tableToDelete: null,
			viewToDelete: null,
			editTable: null,
			editContext: null,
			importContext: null,
			tableToTransfer: null,
			contextToTransfer: null,
			contextToDelete: null,
		}
	},

	mounted() {
		// table
		this.subscribeToEventBus('tables:table:create', () => { this.showModalCreateTable = true })
		this.subscribeToEventBus('tables:table:delete', table => { this.tableToDelete = table })
		this.subscribeToEventBus('tables:table:edit', tableId => { this.editTable = tableId })
		this.subscribeToEventBus('tables:table:transfer', table => { this.tableToTransfer = table })

		// views
		this.subscribeToEventBus('tables:view:reload', () => { this.reload(true) })
		this.subscribeToEventBus('tables:view:edit', view => { this.viewToEdit = { ...view, createView: false } })
		this.subscribeToEventBus('tables:view:create', tableInfos => {
			this.viewToEdit = {
				view: { tableId: tableInfos.tableId, sort: [], filter: [] },
				viewSetting: tableInfos.viewSetting,
				createView: true,
			}
		})
		this.subscribeToEventBus('tables:view:delete', view => { this.viewToDelete = view })

		// columns
		this.subscribeToEventBus('tables:column:create', columnInfo => { this.createColumnInfo = columnInfo })
		this.subscribeToEventBus('tables:column:edit', columnInfo => { this.columnToEdit = columnInfo })
		this.subscribeToEventBus('tables:column:delete', columnInfo => { this.columnToDelete = columnInfo })

		// rows
		this.subscribeToEventBus('tables:row:create', columnsInfo => { this.columnsForRow = columnsInfo })
		this.subscribeToEventBus('tables:row:copy', rowInfo => { this.columnsForRow = { columns: rowInfo.columns, isView: rowInfo.isView, elementId: rowInfo.elementId, prefillData: rowInfo.row?.data } })
		this.subscribeToEventBus('tables:row:edit', rowInfo => { this.editRow = rowInfo })
		this.subscribeToEventBus('tables:row:delete', tableInfo => {
			this.rowsToDelete = tableInfo
		})

		// misc
		this.subscribeToEventBus('tables:modal:import', element => { this.importToElement = element })
		this.subscribeToEventBus('tables:modal:scheme', title => { this.importSchemeTitle = title; this.showImportScheme = true })
		this.subscribeToEventBus('tables:modal:table-scheme-import', payload => { this.importSchemaToTable = payload.table })

		// context
		this.subscribeToEventBus('tables:context:create', () => { this.showModalCreateContext = true })
		this.subscribeToEventBus('tables:context:edit', contextId => { this.editContext = contextId })
		this.subscribeToEventBus('tables:context:import-scheme', contextId => { this.importContext = contextId })
		this.subscribeToEventBus('tables:context:transfer', context => { this.contextToTransfer = context })
		this.subscribeToEventBus('tables:context:delete', context => { this.contextToDelete = context })

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
