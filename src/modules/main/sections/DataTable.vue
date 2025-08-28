<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div v-if="hasViews" class="row space-T">
			<div v-if="showOptions" class="col-4 space-L">
				<h2>
					{{ t('tables', 'Data') }}&nbsp;&nbsp;
					<NcActions :force-menu="true" type="secondary">
						<template #icon>
							<IconTool :size="20" />
						</template>
						<NcActionCaption v-if="canManageElement(table)" :name="t('tables', 'Manage table')" />
						<NcActionButton v-if="canManageElement(table)"
							data-cy="dataTableEditTableBtn"
							:close-after-click="true"
							@click="emit('tables:table:edit', table.id)">
							<template #icon>
								<IconRename :size="20" decorative />
							</template>
							{{ t('tables', 'Edit table') }}
						</NcActionButton>
						<NcActionButton v-if="canManageElement(table)"
							:close-after-click="true" @click="$emit('create-view')">
							<template #icon>
								<PlaylistPlus :size="20" decorative />
							</template>
							{{ t('tables', 'Create view') + (isViewSettingSet ? '*' : '') }}
						</NcActionButton>
						<NcActionButton v-if="canManageTable(table)" :close-after-click="true" @click="$emit('create-column')">
							<template #icon>
								<TableColumnPlusAfter :size="20" decorative title="" />
							</template>
							{{ t('tables', 'Create column') }}
						</NcActionButton>

						<NcActionCaption :name="t('tables', 'Integration')" />
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
								<Connection :size="20" />
							</template>
						</NcActionButton>
					</NcActions>
				</h2>
			</div>
		</div>
		<div class="row">
			<EmptyTable v-if="columns.length === 0" :table="table" @create-column="showCreateColumn = true" />
			<TableView v-else
				:rows="rows"
				:columns="columns"
				:element="table"
				:view-setting.sync="localViewSetting"
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
						<NcActionCaption v-if="canManageElement(table)" :name="t('tables', 'Manage table')" />
						<NcActionButton v-if="canManageElement(table)"
							data-cy="dataTableEditTableBtn"
							:close-after-click="true"
							@click="emit('tables:table:edit', table.id)">
							<template #icon>
								<IconRename :size="20" decorative />
							</template>
							{{ t('tables', 'Edit table') }}
						</NcActionButton>
						<NcActionButton v-if="canManageElement(table) "
							:close-after-click="true"
							data-cy="dataTableCreateViewBtn" @click="$emit('create-view')">
							<template #icon>
								<PlaylistPlus :size="20" decorative />
							</template>
							{{ t('tables', 'Create view') + (isViewSettingSet ? '*' : '') }}
						</NcActionButton>
						<NcActionButton v-if="canManageTable(table)" :close-after-click="true" data-cy="dataTableCreateColumnBtn" @click="$emit('create-column')">
							<template #icon>
								<TableColumnPlusAfter :size="20" decorative title="" />
							</template>
							{{ t('tables', 'Create column') }}
						</NcActionButton>

						<NcActionCaption :name="t('tables', 'Integration')" />
						<NcActionButton v-if="canCreateRowInElement(table)"
							:close-after-click="true"
							data-cy="dataTableExportBtn" @click="$emit('import', table)">
							<template #icon>
								<Import :size="20" decorative title="Import" />
							</template>
							{{ t('tables', 'Import') }}
						</NcActionButton>
						<NcActionButton v-if="canReadData(table)" :close-after-click="true"
							icon="icon-download"
							data-cy="dataTableExportBtn" @click="$emit('download-csv')">
							{{ t('tables', 'Export as CSV') }}
						</NcActionButton>
						<NcActionButton v-if="canShareElement(table)"
							data-cy="dataTableShareBtn"
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
								<Connection :size="20" />
							</template>
						</NcActionButton>
					</NcActions>
				</template>
			</TableView>
		</div>
	</div>
</template>

<script>
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import IconRename from 'vue-material-design-icons/RenameOutline.vue'
import IconTool from 'vue-material-design-icons/TableCog.vue'
import TableView from '../partials/TableView.vue'
import EmptyTable from './EmptyTable.vue'
import Connection from 'vue-material-design-icons/Connection.vue'
import Import from 'vue-material-design-icons/Import.vue'
import { NcActionButton, NcActions, NcActionCaption } from '@nextcloud/vue'
import { mapState } from 'pinia'
import { emit } from '@nextcloud/event-bus'
import { useTablesStore } from '../../../store/store.js'

export default {
	components: {
		IconTool,
		TableView,
		NcActionButton,
		Connection,
		NcActionCaption,
		NcActions,
		TableColumnPlusAfter,
		PlaylistPlus,
		EmptyTable,
		Import,
		IconRename,
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
		showOptions: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			localSelectedRows: this.selectedRows,
			localViewSetting: this.viewSetting,
		}
	},

	computed: {
		...mapState(useTablesStore, ['views']),
		hasViews() {
			return this.views.some(v => v.tableId === this.table.id)
		},
		isViewSettingSet() {
			return !(!this.localViewSetting || ((!this.localViewSetting.hiddenColumns || this.localViewSetting.hiddenColumns.length === 0) && (!this.localViewSetting.sorting) && (!this.localViewSetting.filter || this.localViewSetting.filter.length === 0)))
		},
	},

	watch: {
		localSelectedRows() {
			this.$emit('update:selectedRows', this.localSelectedRows)
		},
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
	},

	methods: {
		emit,
	},
}
</script>
<style lang="scss" scoped>

	.row {
		width: auto;
	}

	h2 {
		display: inline-flex;
		align-items: center;
	}

</style>
