<template>
	<div>
		<div v-if="isLoading" class="icon-loading" />

		<div v-if="!isLoading && activeTable">
			<ElementDescription :active-element="activeTable" />
			<div v-if="hasViews" class="dashboard-content">
				<h3>
					{{ t('tables', 'Views') }}
				</h3>
				<div>
					<table class="table">
						<thead>
							<tr>
								<th>{{ t('tables', 'View') }} </th>
								<th>{{ t('tables', 'Rows number') }} </th>
								<th>{{ t('tables', 'Columns number') }} </th>
								<th>{{ t('tables', 'Last edited') }} </th>
								<th>{{ t('tables', 'Shares') }} </th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="view in getViews" :key="view.id">
								<td>{{ view.emoji + ' ' + view.title }}</td>
								<td>{{ view.rowsCount }}</td>
								<td>{{ view.columns.length }}</td>
								<td>{{ view.lastEditAt }}</td>
								<td>{{ view.hasShares }}</td>
							</tr>
							<tr key="footer">
								<td>{{ t('Tables', 'Total') }}</td>
								<td>{{ activeTable.rowsCount }}</td>
								<td>{{ activeTable.columnsCount }}</td>
								<td>{{ false }}</td>
								<td>{{ false }}</td>
							</tr>
						</tbody>
					</table>
				</div>
				<h3>
					{{ t('tables', 'Actions') }}
				</h3>
				<div class="actions">
					<NcButton v-if="canManageElement(activeTable)"
						type="secondary"
						:close-after-click="true" @click="showCreateColumn = true">
						<template #icon>
							<TableColumnPlusAfter :size="20" decorative title="" />
						</template>
						{{ t('tables', 'Create column') }}
					</NcButton>
					<NcButton v-if="canManageElement(activeTable)"
						type="secondary"
						:close-after-click="true" @click="openCreateViewModal = true">
						<template #icon>
							<PlaylistPlus :size="20" />
						</template>
						{{ t('tables', 'Create view') }}
					</NcButton>
					<NcButton v-if="canManageElement(activeTable)"
						type="secondary"
						:close-after-click="true" @click="openCreateViewModal = true">
						<template #icon>
							<Import :size="20" />
						</template>
						{{ t('tables', 'Import') }}
					</NcButton>
					<NcButton v-if="canManageElement(activeTable)" icon="icon-delete"
						type="error"
						:close-after-click="true" @click="showDeletionConfirmation = true">
						<template #icon>
							<Delete :size="20" />
						</template>
						{{ t('tables', 'Delete') }}
					</NcButton>
				</div>
			</div>
			<div class="table-wrapper">
				<NcView v-if="columns.length > 0"
					:rows="rows"
					:columns="columns"
					:view="activeTable"
					:view-setting="viewSetting"
					:is-view="false"
					@add-filter="addFilter"
					@set-search-string="setSearchString"
					@edit-row="rowId => editRowId = rowId"
					@import="openImportModal"
					@create-column="showCreateColumn = true"
					@create-row="showCreateRow = true"
					@delete-selected-rows="deleteRows"
					@delete-filter="deleteFilter" />
			</div>
			<EmptyTable v-if="columns.length === 0" :table="activeTable" @create-column="showCreateColumn = true" />
		</div>
		<ViewSettings :view="{ tableId: activeTable?.id, sort: [], filter: [] }"
			:create-view="true" :show-modal="openCreateViewModal"
			@close="openCreateViewModal = false" />
		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
		<DeleteRows v-if="rowsToDelete" :rows-to-delete="rowsToDelete" :active-view="activeTable" @cancel="rowsToDelete = null" />
		<DialogConfirmation :description="getTranslatedDescription"
			:title="t('tables', 'Confirm table deletion')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showDeletionConfirmation"
			@confirm="deleteMe"
			@cancel="showDeletionConfirmation = false" />
		<CreateRow :columns="columns"
			:show-modal="showCreateRow"
			@close="showCreateRow = false" />
		<EditRow :columns="columns"
			:row="getEditRow"
			:show-modal="editRowId !== null"
			:out-transition="true"
			@close="editRowId = null" />
		<EditColumn v-if="columnToEdit" :column="columnToEdit" :view="activeTable" @close="columnToEdit = false" />
		<DeleteColumn v-if="columnToDelete" :column-to-delete="columnToDelete" @cancel="columnToDelete = null" />
	</div>
</template>

<script>
import ElementDescription from '../modules/main/sections/ElementDescription.vue'
import { mapState, mapGetters } from 'vuex'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import { NcButton } from '@nextcloud/vue'
import ViewSettings from '../modules/main/modals/ViewSettings.vue'
import Import from 'vue-material-design-icons/Import.vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import { showSuccess } from '@nextcloud/dialogs'
import DialogConfirmation from '../shared/modals/DialogConfirmation.vue'
import CreateColumn from '../modules/main/modals/CreateColumn.vue'
import NcView from '../shared/components/ncTable/NcView.vue'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import DeleteRows from '../modules/main/modals/DeleteRows.vue'
import CreateRow from '../modules/main/modals/CreateRow.vue'
import EditRow from '../modules/main/modals/EditRow.vue'
import EditColumn from '../modules/main/modals/EditColumn.vue'
import DeleteColumn from '../modules/main/modals/DeleteColumn.vue'
import EmptyTable from '../modules/main/sections/EmptyTable.vue'

export default {
	name: 'Dashboard',
	components: {
		ElementDescription,
		NcButton,
		ViewSettings,
		Import,
		TableColumnPlusAfter,
		PlaylistPlus,
		Delete,
		DialogConfirmation,
		CreateColumn,
		NcView,
		DeleteRows,
		CreateRow,
		EditRow,
		EditColumn,
		DeleteColumn,
		EmptyTable,
	},

	mixins: [permissionsMixin],

	data() {
		return {
			localLoading: false,
			lastActiveTableId: null,
			openCreateViewModal: false,
			showDeletionConfirmation: false,
			showCreateColumn: false,
			rowsToDelete: null,
			showCreateRow: false,
			editRowId: null,
			columnToEdit: null,
			columnToDelete: null,
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
		...mapState(['views']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.activeTable?.title })
		},
		getViews() {
			return this.views.filter(v => v.tableId === this.activeTable.id)
		},
		hasViews() {
			return this.getViews.length > 0
		},
		isLoading() {
			return (this.loading || this.localLoading) && (!this.editView)
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
		subscribe('tables:view:edit', view => { this.editView = view })
		subscribe('tables:column:edit', column => { this.columnToEdit = column })
		subscribe('tables:column:delete', column => { this.columnToDelete = column })
		subscribe('tables:view:reload', () => { this.reload() })
	},
	unmounted() {
		unsubscribe('tables:view:edit', view => { this.editView = view })
		unsubscribe('tables:column:edit', column => { this.columnToEdit = column })
		unsubscribe('tables:column:delete', column => { this.columnToDelete = column })
		unsubscribe('tables:view:reload', () => { this.reload() })
	},
	methods: {
		openImportModal(view) {
			emit('tables:modal:import', view)
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

				await this.$store.dispatch('loadColumnsFromBE', { table: this.activeTable })
				if (this.canReadData(this.activeTable)) {
					await this.$store.dispatch('loadRowsFromBE', { tableId: this.activeTable.id })
				} else {
					await this.$store.dispatch('removeRows')
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
		async deleteMe() {
			const table = this.activeTable
			const res = await this.$store.dispatch('removeTable', { tableId: table.id })
			if (res) {
				this.showDeletionConfirmation = false
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: table.emoji ? table.emoji + ' ' : '', table: table.title }))
				await this.$router.push('/').catch(err => err)
			}
		},
	},

}
</script>

<style>
.table {
  border-collapse: collapse;
  border: 1px solid #ccc;
}

.table th,
.table td {
  padding: 8px;
  text-align: left;
  border-right: 4px solid transparent;
}

.table th:last-child,
.table td:last-child {
  border-right: none;
}

.table th {
  background-color: #f2f2f2;
  border-bottom: 2px solid #ccc;
}

.dashboard-content {
	padding: calc(var(--default-grid-baseline) * 4);
}

.actions {
	display: flex;
	flex-direction: row;
}
</style>
