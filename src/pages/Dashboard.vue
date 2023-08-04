<template>
	<div>
		<div v-if="isLoading" class="icon-loading" />

		<div v-if="!isLoading && table">
			<ElementDescription :active-element="table" />

			<div v-if="hasViews" class="row space-B space-T">
				<div class="col-4 space-L">
					<h2 style="display: inline-flex; align-items: center;">
						{{ t('tables', 'Table') }}&nbsp;&nbsp;
						<NcActions :force-menu="true" type="secondary">
							<NcActionButton v-if="canManageElement(table)"
								:close-after-click="true"
								@click="$emit('create-column')">
								<template #icon>
									<TableColumnPlusAfter :size="20" decorative />
								</template>
								{{ t('tables', 'Create column') }}
							</NcActionButton>
							<NcActionButton v-if="canCreateRowInElement(table)"
								:close-after-click="true"
								@click="$emit('import', table)">
								<template #icon>
									<IconImport :size="20" decorative />
								</template>
								{{ t('tables', 'Import') }}
							</NcActionButton>
							<NcActionButton v-if="canShareElement(table)"
								:close-after-click="true"
								icon="icon-share">
								{{ t('tables', 'Share') }}
							</NcActionButton>
							<NcActionButton
								:close-after-click="true">
								{{ t('tables', 'Integration') }}
								<template #icon>
									<Creation :size="20" />
								</template>
							</NcActionButton>
							<NcActionButton v-if="canManageElement(table)"
								type="error"
								:close-after-click="true"
								@click="showDeletionConfirmation = true">
								<template #icon>
									<Delete :size="20" />
								</template>
								{{ t('tables', 'Delete table') }}
							</NcActionButton>
						</NcActions>
					</h2>
					<table class="table">
						<tbody>
							<tr>
								<td>{{ t('tables', 'Title') }}</td>
								<td>{{ table.title }}</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Emoji') }}</td>
								<td>{{ table.emoji }}</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Created at') }}</td>
								<td>{{ table.createdAt | niceDateTime }}</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Created by') }}</td>
								<td>{{ table.createdBy }}</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Ownership') }}</td>
								<td>{{ table.ownership }}</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Table ID') }}</td>
								<td>{{ table.id }}</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Is shared with you') }}</td>
								<td>{{ table.isShared }}</td>
							</tr>
							<tr>
								<td>{{ t('tables', 'Shares') }}</td>
								<td>
									<NcLoadingIcon v-if="loadingTableShares" />
									<div v-else class="inline">
										<div v-for="share in tableShares" :key="share.id">
											<NcAvatar
												:display-name="share.receiverDisplayName"
												:user="share.receiver"
												:is-no-user="share.receiverType !== 'user'" />
										</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div v-if="hasViews" class="row space-T space-B">
				<div class="col-4 space-L">
					<h2>
						{{ t('tables', 'Views') }}&nbsp;&nbsp;
						<NcButton v-if="canManageElement(table)"
							type="secondary"
							:aria-label="t('tables', 'Create view')"
							:close-after-click="true" @click="openCreateViewModal = true">
							<template #icon>
								<PlaylistPlus :size="20" />
							</template>
						</NcButton>
					</h2>
				</div>
				<div class="col-4 space-L">
					<table class="table">
						<thead>
							<tr>
								<th>{{ t('tables', 'View') }} </th>
								<th>{{ t('tables', 'Rows') }} </th>
								<th>{{ t('tables', 'Columns') }} </th>
								<th>{{ t('tables', 'Last edited') }} </th>
								<th>{{ t('tables', 'Shares') }} </th>
								<th>{{ t('tables', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="view in getViews" :key="view.id">
								<td style="display: inline-flex">
									{{ view.emoji + ' ' + view.title }}&nbsp;
									<NcButton :to="'/view/'+view.id" type="tertiary">
										<template #icon>
											<LinkIcon :size="20" />
										</template>
									</NcButton>
								</td>
								<td>{{ view.rowsCount }}</td>
								<td>{{ view.columns.length }}</td>
								<td>{{ view.lastEditAt | niceDateTime }}</td>
								<td v-if="view.hasShares">
									<NcLoadingIcon v-if="loadingViewShares" />
									<div v-else>
										<div v-for="share in viewShares[view.id]" :key="share.id">
											<NcAvatar
												:display-name="share.receiverDisplayName"
												:user="share.receiver"
												:is-no-user="share.receiverType !== 'user'" />
										</div>
									</div>
								</td>
								<td v-else />
								<td class="actions">
									<NcButton v-if="canManageElement(table)"
										type="secondary"
										:aria-label="t('tables', 'Edit view')"
										:close-after-click="true">
										<template #icon>
											<PlaylistEditIcon :size="20" />
										</template>
									</NcButton>
									<NcButton v-if="canManageElement(table)"
										type="error"
										:aria-label="t('tables', 'Delete view')"
										:close-after-click="true">
										<template #icon>
											<Delete :size="20" />
										</template>
									</NcButton>
								</td>
							</tr>
							<tr class="footer">
								<td>{{ t('Tables', 'Total') }}</td>
								<td>{{ table.rowsCount }}</td>
								<td>{{ table.columnsCount }}</td>
								<td />
								<td />
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="row space-T space-B">
				<div class="col-4 space-L">
					<h2>{{ t('tables', 'Data') }}</h2>
				</div>
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
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import { showSuccess } from '@nextcloud/dialogs'
import DialogConfirmation from '../shared/modals/DialogConfirmation.vue'
import TableView from './TableView.vue'
import EmptyTable from '../modules/main/sections/EmptyTable.vue'
import Moment from '@nextcloud/moment'
import IconImport from 'vue-material-design-icons/Import.vue'
import Creation from 'vue-material-design-icons/Creation.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { NcActionButton, NcActions, NcAvatar, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import PlaylistEditIcon from 'vue-material-design-icons/PlaylistEdit.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'

export default {
	name: 'Dashboard',
	components: {
		TableView,
		NcLoadingIcon,
		NcActionButton,
		Creation,
		IconImport,
		NcActions,
		ElementDescription,
		NcButton,
		NcAvatar,
		ViewSettings,
		TableColumnPlusAfter,
		PlaylistPlus,
		PlaylistEditIcon,
		LinkIcon,
		Delete,
		DialogConfirmation,
		EmptyTable,
	},

	filters: {
		niceDateTime(value) {
			return Moment(value, 'YYYY-MM-DD HH:mm:ss').format('lll')
		},
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
			loadingTableShares: true,
			loadingViewShares: true,
			viewShares: {},
			tableShares: [],
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
		table() {
			this.loadShares()
		},
	},

	mounted() {
		this.loadShares()
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

		async getSharesForViewFromBE(viewId) {
			try {
				const res = await axios.get(generateUrl('/apps/tables/share/view/' + viewId))
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch shares.'))
			}
		},

		async getSharesForTableFromBE(tableId) {
			try {
				const res = await axios.get(generateUrl('/apps/tables/share/table/' + tableId))
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch shares.'))
			}
		},

		async loadShares() {
			// load shares for table
			this.loadingTableShares = true
			this.tableShares = await this.getSharesForTableFromBE(this.table.id)
			this.loadingTableShares = false

			// load shares for all views
			this.loadingViewShares = true
			for (const index in this.table.views) {
				const view = this.table.views[index]
				console.debug('view', view)
				if (view.hasShares) {
					this.viewShares[view.id] = await this.getSharesForViewFromBE(view.id)
				}
			}
			this.tableShares = await this.getSharesForTableFromBE(this.table.id)
			this.loadingViewShares = false
		},
	},

}
</script>

<style lang="scss" scoped>
.table {
  border-collapse: collapse;
}

.table td .inline {
	display: inline-flex;
}

.table th,
.table td {
	padding-right: calc(var(--default-grid-baseline) * 4);
	padding-top: calc(var(--default-grid-baseline) * 1);
	padding-bottom: calc(var(--default-grid-baseline) * 1);
	text-align: left;
	background-color: var(--color-main-background-translucent);
	align-items: center;
}

.table th:last-child,
.table td:last-child {
  border-right: none;
}

.table th {
	color: var(--color-text-maxcontrast);
	box-shadow: inset 0 -1px 0 var(--color-border);
}

.table tr:hover, .table tr:hover td {
	background-color: var(--color-background-dark) !important;
}

.dashboard-content {
	padding: calc(var(--default-grid-baseline) * 4);
}

.actions {
	display: flex;
	flex-direction: row;
}

	td.actions {
		display: inline-flex;
	}

	td.actions button {
		margin-left: calc(var(--default-grid-baseline) * 1);
	}

	td a {
		text-decoration: underline;
	}

	.footer td {
		font-weight: bold;
	}

	h2 {
		display: inline-flex;
		align-items: center;
	}

</style>
