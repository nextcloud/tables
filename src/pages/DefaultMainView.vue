<template>
	<div>
		<ElementDescription :active-element="view" :is-table="false" :view-setting="viewSetting" />
		<div class="table-wrapper">
			<EmptyView v-if="columns.length === 0" />
			<TableView v-else
				:rows="rows"
				:columns="columns"
				:element="view"
				:view-setting="viewSetting"
				:is-view="true"
				:selected-rows.sync="localSelectedRows"
				:can-read-rows="canReadData(view)"
				:can-create-rows="canCreateRowInElement(view)"
				:can-edit-rows="canUpdateData(view)"
				:can-delete-rows="canDeleteData(view)"
				:can-create-columns="canManageTable(view)"
				:can-edit-columns="canManageTable(view)"
				:can-delete-columns="canManageTable(view)"
				:can-delete-table="canManageTable(view)">
				<template #actions>
					<NcActions :force-menu="true" :type="isViewSettingSet ? 'secondary' : 'tertiary'">
						<NcActionCaption v-if="canManageElement(view)" :title="t('tables', 'Manage view')" />
						<NcActionButton v-if="canManageElement(view) "
							:close-after-click="true"
							@click="viewToEdit = view">
							<template #icon>
								<PlaylistEdit :size="20" decorative />
							</template>
							{{ t('tables', 'Edit view') }}
						</NcActionButton>
						<NcActionButton v-if="canManageTable(view)" :close-after-click="true" @click="$emit('create-column')">
							<template #icon>
								<TableColumnPlusAfter :size="20" decorative title="" />
							</template>
							{{ t('tables', 'Create column') }}
						</NcActionButton>

						<NcActionCaption :title="t('tables', 'Integration')" />
						<NcActionButton v-if="canCreateRowInElement(view)"
							:close-after-click="true"
							@click="$emit('import', view)">
							<template #icon>
								<IconImport :size="20" decorative title="Import" />
							</template>
							{{ t('tables', 'Import') }}
						</NcActionButton>
						<NcActionButton v-if="canReadData(view)" :close-after-click="true"
							icon="icon-download"
							@click="$emit('download-csv')">
							{{ t('tables', 'Export as CSV') }}
						</NcActionButton>
						<NcActionButton v-if="canShareElement(view)"
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

		<ViewSettings
			:show-modal="viewToEdit !== null"
			:view="viewToEdit"
			:view-setting="viewSetting"
			@close="viewToEdit = null"
			@reload-view="reload(true)" />
	</div>
</template>

<script>
import { mapState } from 'vuex'
import TableView from './TableView.vue'

import ViewSettings from '../modules/main/modals/ViewSettings.vue'
import EmptyView from '../modules/main/sections/EmptyView.vue'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { Filters } from '../shared/components/ncTable/mixins/filter.js'
import { NcActions, NcActionButton, NcActionCaption } from '@nextcloud/vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import IconImport from 'vue-material-design-icons/Import.vue'
import Creation from 'vue-material-design-icons/Creation.vue'
import ElementDescription from '../modules/main/sections/ElementDescription.vue'

export default {
	name: 'DefaultMainView',
	components: {
		EmptyView,
		ViewSettings,
		TableView,
		PlaylistEdit,
		IconImport,
		NcActions,
		NcActionButton,
		TableColumnPlusAfter,
		NcActionCaption,
		Creation,
		ElementDescription,
	},

	mixins: [permissionsMixin],

	props: {
		view: {
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
			lastActiveViewId: null,
			viewToEdit: null,
			localSelectedRows: this.selectedRows,
		}
	},
	computed: {
		...mapState(['activeRowId']),
		isViewSettingSet() {
			return !(!this.viewSetting || ((!this.viewSetting.hiddenColumns || this.viewSetting.hiddenColumns.length === 0) && (!this.viewSetting.sorting) && (!this.viewSetting.filter || this.viewSetting.filter.length === 0)))
		},
		isLoading() {
			return (this.loading || this.localLoading) && (!this.viewToEdit)
		},
	},
	watch: {
		localSelectedRows() {
			this.$emit('update:selectedRows', this.localSelectedRows)
		},
	},
	mounted() {
		subscribe('tables:view:edit', view => { this.viewToEdit = view })
	},
	unmounted() {
		unsubscribe('tables:view:edit', view => { this.viewToEdit = view })
	},
}
</script>
