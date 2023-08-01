<template>
	<div>
		<div v-if="isLoading" class="icon-loading" />

		<div v-if="!isLoading && activeTable">
			<ElementDescription :active-element="activeTable" />
			<div class="dashboard-content">
				<h3>
					{{ t('tables', 'Statistics') }}
				</h3>
				<div>
					<table class="table">
						<tbody>
							<tr>
								<td>{{ t('tables', 'Total rows') }}</td>
								<td class="align-left">
									{{ activeTable.rowsCount }}
								</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Columns') }}</td>
								<td class="align-left">
									{{ activeTable.columnsCount }}
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<h3>
					{{ t('tables', 'Views') }}
				</h3>
				<div>
					<table class="table">
						<thead>
							<tr>
								<th>{{ t('tables', 'View') }} </th>
								<th>{{ t('tables', 'Rows number') }} </th>
								<th>{{ t('tables', 'Last edited') }} </th>
								<th>{{ t('tables', 'Shares') }} </th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="view in activeTable.views" :key="view.id">
								<td>{{ view.emoji + ' ' + view.title }}</td>
								<td>{{ view.rowsCount }}</td>
								<td>{{ view.lastEditAt }}</td>
								<td>{{ view.hasShares }}</td>
							</tr>
						</tbody>
					</table>
				</div>
				<h3>
					{{ t('tables', 'Actions') }}
				</h3>
				<div class="actions">
					<NcButton v-if="canManageTable(activeTable)"
						type="secondary"
						:close-after-click="true" @click="showCreateColumn = true">
						<template #icon>
							<TableColumnPlusAfter :size="20" decorative title="" />
						</template>
						{{ t('tables', 'Create column') }}
					</NcButton>
					<NcButton v-if="canManageTable(activeTable)"
						type="secondary"
						:close-after-click="true" @click="openCreateViewModal = true">
						<template #icon>
							<PlaylistPlus :size="20" />
						</template>
						{{ t('tables', 'Create view') }}
					</NcButton>
					<NcButton v-if="canManageTable(activeTable)"
						type="secondary"
						:close-after-click="true" @click="openCreateViewModal = true">
						<template #icon>
							<Import :size="20" />
						</template>
						{{ t('tables', 'Import') }}
					</NcButton>
					<NcButton v-if="canManageTable(activeTable)" icon="icon-delete"
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
					@add-filter="addFilter"
					@set-search-string="setSearchString"
					@edit-row="rowId => editRowId = rowId"
					@import="openImportModal"
					@create-column="showCreateColumn = true"
					@create-row="showCreateRow = true"
					@delete-selected-rows="deleteRows"
					@delete-filter="deleteFilter" />
			</div>
		</div>
		<ViewSettings :view="{ tableId: activeTable?.id, sort: [], filter: [] }"
			:create-view="true" :show-modal="openCreateViewModal"
			@close="openCreateViewModal = false" />
		<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
		<DialogConfirmation :description="getTranslatedDescription"
			:title="t('tables', 'Confirm table deletion')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showDeletionConfirmation"
			@confirm="deleteMe"
			@cancel="showDeletionConfirmation = false" />
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
	},

	mixins: [permissionsMixin],

	data() {
		return {
			localLoading: false,
			lastActiveTableId: null,
			openCreateViewModal: false,
			showDeletionConfirmation: false,
			showCreateColumn: false,
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
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.activeTable?.title })
		},
		isLoading() {
			return (this.loading || this.localLoading) && (!this.editView)
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
