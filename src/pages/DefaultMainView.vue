<template>
	<div>
		<div v-if="isLoading" class="icon-loading" />

		<div v-if="!isLoading && activeTable">
			<ElementDescription />

			<div class="table-wrapper">
				<NcTable v-if="columns.length > 0"
					:rows="rows"
					:columns="columns"
					:table="activeTable"
					:view-setting="viewSetting"
					@add-filter="addFilter"
					@set-search-string="setSearchString"
					@edit-row="rowId => editRowId = rowId"
					@import="openImportModal"
					@create-column="showCreateColumn = true"
					@edit-columns="showEditColumns = true"
					@create-row="showCreateRow = true"
					@create-view="showCreateView = true"
					@delete-selected-rows="deleteRows"
					@delete-filter="deleteFilter" />
			</div>

			<EmptyTable v-if="columns.length === 0" @create-column="showCreateColumn = true" />
		</div>

		<CreateRow :columns="columns"
			:show-modal="showCreateRow"
			@close="showCreateRow = false" />
		<CreateView :columns="columns"
			:show-modal="showCreateView"
			@close="showCreateView = false" />
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
import ElementDescription from '../modules/main/sections/ElementDescription.vue'
import { mapState, mapGetters } from 'vuex'
import NcTable from '../shared/components/ncTable/NcTable.vue'
import CreateRow from '../modules/main/modals/CreateRow.vue'
import CreateView from '../modules/main/modals/CreateView.vue'
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
		ElementDescription,
		NcTable,
		CreateRow,
		CreateView,
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
			showCreateView: false,
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
			viewSetting: state => state.data.viewSetting,
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

				await this.$store.dispatch('resetViewSetting')

				await this.$store.dispatch('loadColumnsFromBE', { tableId: this.activeTable.id })

				if (this.canReadElement(this.activeTable)) {
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
