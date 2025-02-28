<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<ElementTitle :active-element="view" :is-table="false" :view-setting.sync="localViewSetting" />
		<TableDescription :description="view.description" :read-only="true" />
		<div class="table-wrapper">
			<EmptyView v-if="columns.length === 0" :view="view" />
			<TableView v-else
				:rows="rows"
				:columns="columns"
				:element="view"
				:view-setting.sync="localViewSetting"
				:is-view="true"
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
						<NcActionCaption v-if="canManageElement(view)" :name="t('tables', 'Manage view')" />
						<NcActionButton v-if="canManageElement(view) "
							:close-after-click="true"
							@click="editView">
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

						<NcActionCaption :name="t('tables', 'Integration')" />
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
import TableView from '../partials/TableView.vue'

import EmptyView from './EmptyView.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import { emit } from '@nextcloud/event-bus'
import { NcActions, NcActionButton, NcActionCaption } from '@nextcloud/vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import IconImport from 'vue-material-design-icons/Import.vue'
import Connection from 'vue-material-design-icons/Connection.vue'
import ElementTitle from './ElementTitle.vue'
import TableDescription from './TableDescription.vue'

export default {
	components: {
		TableDescription,
		EmptyView,
		TableView,
		PlaylistEdit,
		IconImport,
		NcActions,
		NcActionButton,
		TableColumnPlusAfter,
		NcActionCaption,
		Connection,
		ElementTitle,
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

	},

	data() {
		return {
			localLoading: false,
			lastActiveViewId: null,
			localViewSetting: this.viewSetting,
		}
	},
	computed: {
		isViewSettingSet() {
			return !(!this.localViewSetting || ((!this.localViewSetting.hiddenColumns || this.localViewSetting.hiddenColumns.length === 0) && (!this.localViewSetting.sorting) && (!this.localViewSetting.filter || this.localViewSetting.filter.length === 0)))
		},
	},
	watch: {
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
	},
	methods: {
		editView() {
			emit('tables:view:edit', { view: this.view, viewSetting: this.localViewSetting })
		},
	},
}
</script>
