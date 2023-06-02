<template>
	<div style="height:100%">
		<div v-if="isLoading" class="icon-loading" />

		<div v-if="!isLoading && activeTable" class="table-page-view">
			<TableDescription />

			<div class="table-wrapper">
				<NcTable v-if="columns.length > 0"
					:rows="rows"
					:columns="columns"
					:table="activeTable"
					:view="view"
					@add-filter="addFilter"
					@set-search-string="setSearchString"
					@edit-row="rowId => editRowId = rowId"
					@import="openImportModal"
					@create-column="showCreateColumn = true"
					@edit-columns="showEditColumns = true"
					@create-row="showCreateRow = true"
					@delete-selected-rows="deleteRows"
					@delete-filter="deleteFilter" />
			</div>

			<EmptyTable v-if="columns.length === 0" @create-column="showCreateColumn = true" />
		</div>

		<CreateRow :columns="columns"
			:show-modal="showCreateRow"
			@close="showCreateRow = false" />
		<EditRow :columns="columns"
			:row="getEditRow"
			:show-modal="editRowId !== null"
			:out-transition="true"
			@close="editRowId = null" />
		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
		<EditColumns :show-modal="showEditColumns" @close="showEditColumns = false" />
		<DeleteRows v-if="rowsToDelete" :rows-to-delete="rowsToDelete" @cancel="rowsToDelete = null" />
	</div>
</template>

<script>
import TableDescription from '../modules/main/sections/TableDescription.vue'
import { mapState, mapGetters } from 'vuex'
import NcTable from '../shared/components/ncTable/NcTable.vue'
import CreateRow from '../modules/main/modals/CreateRow.vue'
import EditRow from '../modules/main/modals/EditRow.vue'
import CreateColumn from '../modules/main/modals/CreateColumn.vue'
import EditColumns from '../modules/main/modals/EditColumns.vue'
import DeleteRows from '../modules/main/modals/DeleteRows.vue'
import EmptyTable from '../modules/main/sections/EmptyTable.vue'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DefaultMainView',
	components: {
		EmptyTable,
		DeleteRows,
		TableDescription,
		NcTable,
		CreateRow,
		EditRow,
		CreateColumn,
		EditColumns,
	},

	mixins: [permissionsMixin],

	data() {
		return {
			localLoading: false,
			lastActiveTableId: null,
			showCreateRow: false,
			editRowId: null,
			showCreateColumn: false,
			showEditColumns: false,
			rowsToDelete: null,
		}
	},
	computed: {
		...mapState({
			columns: state => state.data.columns,
			loading: state => state.data.loading,
			rows: state => state.data.rows,
			view: state => state.data.view,
		}),
		...mapGetters(['activeTable']),
		isLoading() {
			return this.loading || this.localLoading
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
	},
	watch: {
		activeTable() {
			this.reload()
		},
	},
	mounted() {
		this.reload()
	},
	methods: {
		openImportModal(table) {
			emit('tables:modal:import', table)
		},
		deleteFilter(id) {
			this.$store.dispatch('deleteFilter', { id })
		},
		deleteRows(rowIds) {
			this.rowsToDelete = rowIds
		},
		async reload() {
			if (!this.activeTable) {
				return
			}

			if (this.activeTable.id !== this.lastActiveTableId) {
				this.localLoading = true
				await this.$store.dispatch('loadColumnsFromBE', { tableId: this.activeTable.id })

				if (this.canReadTable(this.activeTable)) {
					await this.$store.dispatch('loadRowsFromBE', { tableId: this.activeTable.id })
				}
				this.lastActiveTableId = this.activeTable.id
				this.localLoading = false
			}
		},
		addFilter(filterObject) {
			this.$store.dispatch('addFilter', filterObject)
		},
		setSearchString(str) {
			this.$store.dispatch('setSearchString', { str })
		},
	},
}
</script>

<style>
.table-page-view {
	display: flex;
	flex-flow: column;
	height: 100%;
}

.table-wrapper {
	overflow: hidden;
}
</style>
