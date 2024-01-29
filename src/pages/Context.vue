<template>
	<div v-if="dataReady">
		<div v-for="id in ids">
			<NcTable 
            :key="id" 
            :columns="tableData['columns'+id]" 
            :rows="tableData['rows'+id]" 
            :table-id="id" 
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
import Vuex, { mapState } from 'vuex'
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
			ids: [43, 46],
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
		setActiveElement(tableId) {
			this.$store.commit('setActiveTableId', parseInt(tableId))
		},
		createColumn(tableId) {
			this.setActiveElement(tableId)
			emit('tables:column:create')
		},
		editColumn(column, tableId) {
			this.setActiveElement(tableId)
			emit('tables:column:edit', column)
		},
		deleteColumn(column, tableId) {
			this.setActiveElement(tableId)
			emit('tables:column:delete', column)
		},
		createRow(tableId) {
            const colId = "columns"+tableId
			this.setActiveElement(tableId)
			emit('tables:row:create', this.tableData[colId])
		},
		editRow(rowId, tableId) {
            const colId = "columns"+tableId
            const rowIdState = "rows"+tableId
			this.setActiveElement(tableId)
			emit('tables:row:edit', { row: this.tableData[rowIdState].find(r => r.id === rowId), columns: this.tableData[colId] })
		},
		deleteSelectedRows(rows, tableId) {
			this.setActiveElement(tableId)
			emit('tables:row:delete', rows)
		},

		toggleShare(tableId) {
			this.setActiveElement()
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
		actionShowIntegration(tableId) {
			this.setActiveElement()
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
		},
		openImportModal(element) {
			this.setActiveElement(element.id)
			emit('tables:modal:import', { element, isView: this.isView })
		},
	},
}

</script>

<style scoped lang="scss">

</style>
