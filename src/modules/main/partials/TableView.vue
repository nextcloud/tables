<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcTable v-if="columns.length > 0"
		:rows="rows"
		:columns="columns"
		:element-id="element.id"
		:is-view="isView"
		:download-title="element.title"
		:view-setting.sync="localViewSetting"
		:can-read-rows="canReadRows"
		:can-create-rows="canCreateRows"
		:can-edit-rows="canEditRows"
		:can-delete-rows="canDeleteRows"
		:can-create-columns="canCreateColumns"
		:can-edit-columns="canEditColumns"
		:can-delete-columns="canDeleteColumns"
		:can-delete-table="canDeleteTable"
		@import="openImportModal"
		@create-column="createColumn"
		@edit-column="editColumn"
		@delete-column="deleteColumn"
		@create-row="createRow"
		@edit-row="editRow"
		@delete-selected-rows="deleteSelectedRows">
		<template #actions>
			<slot name="actions" />
		</template>
	</NcTable>
</template>

<script>

import NcTable from '../../../shared/components/ncTable/NcTable.vue'
import { emit } from '@nextcloud/event-bus'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'TableView',

	components: {
		NcTable,
	},

	mixins: [permissionsMixin],

	props: {
		rows: {
			type: Array,
			default: () => [],
		},
		columns: {
			type: Array,
			default: () => [],
		},
		element: {
			type: Object,
			default: () => {},
		},
		viewSetting: {
			type: Object,
			default: null,
		},
		isView: {
			type: Boolean,
			default: true,
		},
		canReadRows: {
			type: Boolean,
			default: true,
		},
		canCreateRows: {
			type: Boolean,
			default: true,
		},
		canEditRows: {
			type: Boolean,
			default: true,
		},
		canDeleteRows: {
			type: Boolean,
			default: true,
		},
		canCreateColumns: {
			type: Boolean,
			default: true,
		},
		canEditColumns: {
			type: Boolean,
			default: true,
		},
		canDeleteColumns: {
			type: Boolean,
			default: true,
		},
		canDeleteTable: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			localViewSetting: this.viewSetting,
		}
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
		createColumn() {
			emit('tables:column:create', { isView: this.isView, element: this.element })
		},
		editColumn(column) {
			emit('tables:column:edit', { column, isView: this.isView, elementId: this.element.id })
		},
		deleteColumn(column) {
			emit('tables:column:delete', { column, isView: this.isView, elementId: this.element.id })
		},
		createRow() {
			emit('tables:row:create', { columns: this.columns, isView: this.isView, elementId: this.element.id })
		},
		editRow(rowId) {
			emit('tables:row:edit', { row: this.rows.find(r => r.id === rowId), columns: this.columns, isView: this.isView, element: this.element })
		},
		deleteSelectedRows(rows) {
			emit('tables:row:delete', { rows, isView: this.isView, elementId: this.element.id })
		},

		toggleShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
		actionShowIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
		},
		openImportModal(element) {
			emit('tables:modal:import', { element, isView: this.isView })
		},
	},
}
</script>
