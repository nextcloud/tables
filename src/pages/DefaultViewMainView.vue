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
					@edit-view="viewId => editViewId = viewId"
					@import="openImportModal"
					@create-column="showCreateColumn = true"
					@edit-columns="showEditColumns = true"
					@create-row="showCreateRow = true"
					@delete-selected-rows="deleteRows"
					@delete-filter="deleteFilter" />
			</div>

			<div v-if="columns.length === 0" @create-column="showCreateColumn = true">
				Empty View
			</div>
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
			:show-modal="editViewId !== null"
			:view="activeView"
			@close="editViewId = null" />
		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
		<EditColumns :show-modal="showEditColumns" @close="showEditColumns = false" />
		<DeleteRows v-if="rowsToDelete" :rows-to-delete="rowsToDelete" @cancel="rowsToDelete = null" />
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
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'DefaultViewMainView',
	components: {
		EditView,
		DeleteRows,
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
			editViewId: null,
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
			if (!this.activeView) {
				return
			}

			if (this.activeView.id !== this.lastActiveViewId) {
				this.localLoading = true

				await this.$store.dispatch('resetViewSetting')

				await this.$store.dispatch('loadColumnsFromBE', { viewId: this.activeView.id })
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
