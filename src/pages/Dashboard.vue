<template>
	<div>
		<div v-if="isLoading" class="icon-loading" />

		<div v-if="!isLoading && table">
			<ElementDescription :active-element="table" />
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
								<td>{{ table.rowsCount }}</td>
								<td>{{ table.columnsCount }}</td>
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
					<NcButton v-if="canManageElement(table)"
						type="secondary"
						:close-after-click="true" @click="showCreateColumn = true">
						<template #icon>
							<TableColumnPlusAfter :size="20" decorative title="" />
						</template>
						{{ t('tables', 'Create column') }}
					</NcButton>
					<NcButton v-if="canManageElement(table)"
						type="secondary"
						:close-after-click="true" @click="openCreateViewModal = true">
						<template #icon>
							<PlaylistPlus :size="20" />
						</template>
						{{ t('tables', 'Create view') }}
					</NcButton>
					<NcButton v-if="canManageElement(table)"
						type="secondary"
						:close-after-click="true" @click="$emit('import', table)">
						<template #icon>
							<Import :size="20" />
						</template>
						{{ t('tables', 'Import') }}
					</NcButton>
					<NcButton v-if="canManageElement(table)" icon="icon-delete"
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
				<EmptyTable v-if="columns.length === 0" :table="table" @create-column="showCreateColumn = true" />
				<TableView v-else
					:rows="rows"
					:columns="columns"
					:element="table"
					:view-setting="viewSetting"
					:is-view="false"
					:selected-rows.sync="localSelectedRows"
					:can-read-rows="canReadData(table)"
					:can-create-rows="canCreateRowInElement(table)"
					:can-edit-rows="canUpdateData(table)"
					:can-delete-rows="canDeleteData(table)"
					:can-create-columns="canManageTable(table)"
					:can-edit-columns="canManageTable(table)"
					:can-delete-columns="canManageTable(table)"
					:can-delete-table="canManageTable(table)">
					<template #actions>
						<NcActions :force-menu="true" :type="isViewSettingSet ? 'secondary' : 'tertiary'">
							<NcActionCaption v-if="canManageElement(table)" :title="t('tables', 'Manage table')" />
							<NcActionButton v-if="canManageElement(table) "
								:close-after-click="true"
								@click="openCreateViewModal = true">
								<template #icon>
									<PlaylistPlus :size="20" decorative />
								</template>
								{{ t('tables', 'Create view') }}
							</NcActionButton>
							<NcActionButton v-if="canManageTable(table)" :close-after-click="true" @click="$emit('create-column')">
								<template #icon>
									<TableColumnPlusAfter :size="20" decorative title="" />
								</template>
								{{ t('tables', 'Create column') }}
							</NcActionButton>

							<NcActionCaption :title="t('tables', 'Integration')" />
							<NcActionButton v-if="canCreateRowInElement(table)"
								:close-after-click="true"
								@click="$emit('import', table)">
								<template #icon>
									<Import :size="20" decorative title="Import" />
								</template>
								{{ t('tables', 'Import') }}
							</NcActionButton>
							<NcActionButton v-if="canReadData(table)" :close-after-click="true"
								icon="icon-download"
								@click="$emit('download-csv')">
								{{ t('tables', 'Export as CSV') }}
							</NcActionButton>
							<NcActionButton v-if="canShareElement(table)"
								:close-after-click="true"
								icon="icon-share"
								@click="$emit('toggle-share')">
								{{ t('tables', 'Share') }}
							</NcActionButton>
							<NcActionButton
								:close-after-click="true"
								@click="$emit('show-integration')">
								{{ t('tables', 'Integration') }}
								<template #icon>
									<Creation :size="20" />
								</template>
							</NcActionButton>
						</NcActions>
					</template>
				</TableView>
			</div>
		</div>
		<ViewSettings :view="{ tableId: table?.id, sort: [], filter: [], columns: columns.map(col => col.id) }"
			:create-view="true" :show-modal="openCreateViewModal"
			:view-setting="viewSetting"
			@close="openCreateViewModal = false" />
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
import { mapState } from 'vuex'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import ViewSettings from '../modules/main/modals/ViewSettings.vue'
import Import from 'vue-material-design-icons/Import.vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import { showSuccess } from '@nextcloud/dialogs'
import DialogConfirmation from '../shared/modals/DialogConfirmation.vue'
import TableView from './TableView.vue'
import EmptyTable from '../modules/main/sections/EmptyTable.vue'
import { NcActions, NcActionButton, NcActionCaption, NcButton } from '@nextcloud/vue'
import Creation from 'vue-material-design-icons/Creation.vue'

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
		TableView,
		EmptyTable,
		NcActions,
		NcActionButton,
		NcActionCaption,
		Creation,
	},

	mixins: [permissionsMixin],

	props: {
		table: {
			type: Object,
			default: null,
		},
		columns: {
			type: Array,
			default: null,
		},
		rows: {
			type: Array,
			default: null,
		},
		viewSetting: {
			type: Object,
			default: null,
		},
		selectedRows: {
			type: Array,
			default: null,
		},
	},

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
			localSelectedRows: this.selectedRows,
		}
	},
	computed: {
		...mapState(['views']),
		isViewSettingSet() {
			return !(!this.viewSetting || ((!this.viewSetting.hiddenColumns || this.viewSetting.hiddenColumns.length === 0) && (!this.viewSetting.sorting) && (!this.viewSetting.filter || this.viewSetting.filter.length === 0)))
		},
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.table?.title })
		},
		getViews() {
			return this.views.filter(v => v.tableId === this.table.id)
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
		localSelectedRows() {
			this.$emit('update:selectedRows', this.localSelectedRows)
		},
	},
	methods: {
		async deleteMe() {
			const table = this.table
			const res = await this.$store.dispatch('removeTable', { tableId: table.id })
			if (res) {
				this.showDeletionConfirmation = false
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: table.emoji ? table.emoji + ' ' : '', table: table.title }))
				await this.$router.push('/').catch(err => err)
			}
		},
		createView() {
			this.openCreateViewModal = true
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
