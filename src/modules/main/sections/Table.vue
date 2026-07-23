<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<ElementTitle v-model:view-setting="localViewSetting" :active-element="table" />
		<TableDescription :description="table.description" :read-only="true" />
		<Dashboard v-if="hasViews"
			:table="table"
			@create-column="$emit('create-column')"
			@import="$emit('import')"
			@toggle-share="$emit('toggle-share')"
			@show-integration="$emit('show-integration')"
			@create-view="createView" />
		<DataTable v-model:view-setting="localViewSetting" :table="table" :columns="columns" :rows="rows"
			@create-column="$emit('create-column')"
			@import="$emit('import')"
			@download-csv="$emit('download-csv')"
			@download-filtered-csv="rows => $emit('download-filtered-csv', rows)"
			@toggle-share="$emit('toggle-share')"
			@show-integration="$emit('show-integration')"
			@create-view="createView" />
	</div>
</template>

<script>
import TableDescription from './TableDescription.vue'
import ElementTitle from './ElementTitle.vue'
import Dashboard from './Dashboard.vue'
import DataTable from './DataTable.vue'
import { mapState } from 'pinia'
import { emit } from '@nextcloud/event-bus'
import { useTablesStore } from '../../../store/store.js'

export default {
	components: {
		TableDescription,
		ElementTitle,
		Dashboard,
		DataTable,
	},

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
	},

	emits: [
		'create-column',
		'download-csv',
		'download-filtered-csv',
		'import',
		'show-integration',
		'toggle-share',
		'update:viewSetting',
	],
	data() {
		return {
			localViewSetting: this.viewSetting,
		}
	},
	computed: {
		...mapState(useTablesStore, ['views']),
		hasViews() {
			return this.views.some(v => v.tableId === this.table.id)
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
		createView() {
			emit('tables:view:create', { tableId: this.table.id, viewSetting: this.viewSetting.length > 0 ? this.viewSetting : this.localViewSetting })
		},
	},
}
</script>
