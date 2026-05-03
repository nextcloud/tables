<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<ElementTitle :active-element="element" :is-table="false" :view-setting.sync="localViewSetting" :is-form-mode="isFormMode" />
		<TableDescription :description="element.description" :read-only="true" />
		<div class="table-wrapper">
			<EmptyView v-if="columns.length === 0" :view="element" />
			<TableView v-else :rows="rows" :columns="columns" :element="element"
				:can-read-rows="element.onSharePermissions.read"
				:can-create-rows="element.onSharePermissions.create"
				:can-edit-rows="element.onSharePermissions.update"
				:can-delete-rows="element.onSharePermissions.delete"
				:can-create-columns="false"
				:can-edit-columns="false"
				:can-delete-columns="false"
				:can-delete-table="false"
				:is-form-mode="isFormMode">
				<template #actions="{ isFiltered, onExportFiltered }">
					<NcActions :force-menu="true" type="tertiary">
						<NcActionButton :close-after-click="true" data-cy="dataTableExportBtn"
							@click="$emit('download-csv')">
							<template #icon>
								<TrayArrowDown :size="20" decorative />
							</template>
							{{ t('tables', 'Export all rows') }}
						</NcActionButton>
						<NcActionButton v-if="isFiltered" :close-after-click="true"
							data-cy="dataTableExportFilteredBtn"
							@click="onExportFiltered">
							<template #icon>
								<TrayArrowDown :size="20" decorative />
							</template>
							{{ t('tables', 'Export filtered rows') }}
						</NcActionButton>
					</NcActions>
				</template>
			</TableView>
		</div>
		<CreateRow
			:columns="columns"
			:is-view="false"
			:element-id="element.id"
			:is-form-mode="isFormMode"
			:show-modal="showCreateRow"
			@close="showCreateRow = false" />
		<DeleteRows
			v-if="rowsToDelete"
			:rows-to-delete="rowsToDelete?.rows"
			:element-id="element.id"
			:is-view="false"
			@cancel="rowsToDelete = null" />
		<EditRow
			:columns="editRow?.columns"
			:row="editRow?.row"
			:is-view="false"
			:element="element"
			:show-modal="editRow !== null"
			:out-transition="true"
			@close="editRow = null" />
	</div>
</template>

<script>
import TableView from '../partials/TableView.vue'
import EmptyView from './EmptyView.vue'
import TableDescription from './TableDescription.vue'
import ElementTitle from './ElementTitle.vue'
import { NcActions, NcActionButton } from '@nextcloud/vue'
import CreateRow from '../../modals/CreateRow.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import EditRow from '../../modals/EditRow.vue'
import DeleteRows from '../../modals/DeleteRows.vue'
import TrayArrowDown from 'vue-material-design-icons/TrayArrowDown.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'PublicElement',
	components: {
		CreateRow,
		DeleteRows,
		EditRow,
		EmptyView,
		TrayArrowDown,
		TableView,
		NcActions,
		NcActionButton,
		TableDescription,
		ElementTitle,
	},

	props: {
		element: {
			type: Object,
			default: null,
		},
		columns: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			showCreateRow: false,
			editRow: null,
			rowsToDelete: null,
		}
	},

	computed: {
		isFormMode() {
			return this.element.onSharePermissions.create && !this.element.onSharePermissions.read
		},
	},

	mounted() {
		subscribe('tables:row:create', () => {
			this.showCreateRow = true
		})
		subscribe('tables:row:edit', rowInfo => {
			this.editRow = rowInfo
		})
		subscribe('tables:row:delete', tableInfo => {
			this.rowsToDelete = tableInfo
		})
	},

	unmounted() {
		unsubscribe('tables:row:create')
		unsubscribe('tables:row:edit')
		unsubscribe('tables:row:delete')
	},

	methods: {
		t,
	},
}
</script>
