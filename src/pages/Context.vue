<template>
	<div v-if="dataReady">
		<div v-for="id in ids">
			<NcTable 
            :key="id" 
            :columns="tableData['columns'+id]" 
            :rows="tableData['rows'+id]" 
            :element-id="id"
            :is-view="false"
            :can-read-rows="true"
            :can-create-rows="true"
            :can-edit-rows="true"
            :can-delete-rows="true"
            :can-create-columns="true"
            :can-edit-columns="true"
            :can-delete-columns="true"
            :can-delete-table="true"
            :can-select-rows="true"
            :can-hide-rows="true"
            :can-filter="true"
            :show-actions="true"
            @import="openImportModal"
            @create-column="createColumn"
            @edit-column="editColumn"
            @delete-column="deleteColumn"
            @create-row="createRow"
            @edit-row="editRow"
            @delete-selected-rows="deleteSelectedRows"
            />
		</div>
        <MainModals />
	</div>
</template>

<script>
import NcTable from '../shared/components/ncTable/NcTable.vue'
import MainModals from '../modules/modals/Modals.vue'
import Vuex from 'vuex'
import Vue from 'vue'
import { emit } from '@nextcloud/event-bus'

Vue.use(Vuex)

export default {
	components: {
		NcTable,
		MainModals,
	},

	data() {
		return {
			dataReady: false,
			ids: [1, 2, 3], //replace with existing table ids
		}
	},

	computed: {
		tableData() {
			const data = {}
			this.ids.forEach((id) => {
                const rowId = "rows"+id
                const colId = "columns"+id
                data[colId] = this.$store.state.data.columns[id]
                data[rowId] = this.$store.state.data.rows[id]
			})
			return data
		},
	},

	async beforeMount() {
		for (const id of this.ids) {
			await this.$store.dispatch('loadColumnsFromBE', {
				viewId: null,
				tableId: id,
			})
			await this.$store.dispatch('loadRowsFromBE', {
				viewId: null,
				tableId: id,
			})
		}
		this.dataReady = true
	},

	methods: {
        createColumn() {
			// emit('tables:column:create', { isView: this.isView, element: this.element })
		},
		editColumn(column, elementId, isView) {
			emit('tables:column:edit', { column, isView, elementId })
		},
		deleteColumn(column, elementId, isView) {
			emit('tables:column:delete', { column, isView, elementId })
		},
		createRow( elementId, isView) {
            const colId = "columns"+elementId
			emit('tables:row:create', {columns: this.tableData[colId], isView, elementId})
		},
		editRow(rowId, elementId, isView) {
            // const colId = "columns"+elementId
            // const rowIdState = "rows"+elementId
			// emit('tables:row:edit', { row: this.tableData[rowIdState].find(r => r.id === rowId), columns: this.tableData[colId] })
		},
		deleteSelectedRows(rows, elementId, isView) {
			// emit('tables:row:delete', rows, isView, elementId)
		},

		toggleShare(tableId) {
			// emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
		actionShowIntegration() {
			// emit('tables:sidebar:integration', { open: true, tab: 'integration' })
		},
		openImportModal(element) {
			// emit('tables:modal:import', { element, isView: false })
		},
	},
}

</script>

<style scoped lang="scss">

</style>