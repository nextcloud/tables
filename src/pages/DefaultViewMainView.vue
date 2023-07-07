<template>
	<div>
		<div v-if="isLoading" class="icon-loading" />

		<div v-if="!isLoading && activeView">
			<ElementDescription />

			<div class="table-wrapper">
				<NcView v-if="columns.length > 0"
					:rows="rows"
					:columns="columns"
					:view="activeView"
					:view-setting="viewSetting"
					@add-filter="addFilter"
					@set-search-string="setSearchString"
					@edit-row="rowId => editRowId = rowId"
					@edit-view="editView = activeView"
					@import="openImportModal"
					@create-column="showCreateColumn = true"
					@edit-columns="showEditColumns = true"
					@create-row="showCreateRow = true"
					@delete-selected-rows="deleteRows"
					@delete-filter="deleteFilter" />
			</div>

			<EmptyTable v-if="columns.length === 0 && activeView.isBaseView" @create-column="showCreateColumn = true" />
			<EmptyView v-if="columns.length === 0 && !activeView.isBaseView" @open-edit-view=" editView = activeView" />
		</div>

		<CreateRow :columns="columns"
			:show-modal="showCreateRow"
			@close="showCreateRow = false" />
		<EditRow :columns="columns"
			:row="getEditRow"
			:show-modal="editRowId !== null"
			:out-transition="true"
			@close="editRowId = null" />
		<EditView
			:show-modal="editView !== null"
			:view="editView"
			@close="editView = null"
			@reload-view="reload(true)" />
		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
		<EditColumns :show-modal="showEditColumns" @close="showEditColumns = false" />
		<DeleteRows v-if="rowsToDelete" :rows-to-delete="rowsToDelete" @cancel="rowsToDelete = null" />
		<DeleteColumn v-if="columnToDelete" :column-to-delete="columnToDelete" @cancel="columnToDelete = null" />
	</div>
</template>

<script>
import ElementDescription from '../modules/main/sections/ElementDescription.vue'
import { mapState, mapGetters } from 'vuex'
import NcView from '../shared/components/ncTable/NcView.vue'
import CreateRow from '../modules/main/modals/CreateRow.vue'
import EditRow from '../modules/main/modals/EditRow.vue'
import EditView from '../modules/main/modals/EditView.vue'
import CreateColumn from '../modules/main/modals/CreateColumn.vue'
import EditColumns from '../modules/main/modals/EditColumns.vue'
import DeleteRows from '../modules/main/modals/DeleteRows.vue'
import DeleteColumn from '../modules/main/modals/DeleteColumn.vue'
import EmptyTable from '../modules/main/sections/EmptyTable.vue'
import EmptyView from '../modules/main/sections/EmptyView.vue'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

export default {
	name: 'DefaultViewMainView',
	components: {
		EmptyTable,
		EmptyView,
		EditView,
		DeleteRows,
		DeleteColumn,
		ElementDescription,
		NcView,
		CreateRow,
		EditRow,
		CreateColumn,
		EditColumns,
	},

	mixins: [permissionsMixin],

	data() {
		return {
			localLoading: false,
			lastActiveViewId: null,
			showCreateRow: false,
			editRowId: null,
			editView: null,
			showCreateColumn: false,
			showEditColumns: false,
			columnToDelete: null,
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
		...mapGetters(['activeView']),
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
		activeView() {
			this.reload()
		},
	},
	mounted() {
		this.reload()
		subscribe('tables:column:delete', column => { this.columnToDelete = column })
		subscribe('tables:view:reload', () => { this.reload(true) })
	},
	unmounted() {
		unsubscribe('tables:column:delete', column => { this.columnToDelete = column })
		unsubscribe('tables:view:reload', () => { this.reload(true) })
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
		async reload(force = false) {
			if (!this.activeView) {
				return
			}

			if (this.activeView.id !== this.lastActiveViewId || force) {
				this.localLoading = true

				await this.$store.dispatch('resetViewSetting')

				await this.$store.dispatch('loadColumnsFromBE', { view: this.activeView })
				if (this.canReadElement(this.activeView)) {
					await this.$store.dispatch('loadRowsFromBE', { viewId: this.activeView.id })
				}
				this.lastActiveViewId = this.activeView.id
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
