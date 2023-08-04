<template>
	<div>
		<ElementDescription :active-element="table" />
		<Dashboard :table="table"
			@create-column="$emit('create-column')"
			@import="$emit('import')"
			@toggle-share="$emit('toggle-share')"
			@show-integration="$emit('show-integration')"
			@create-view="createView" />
		<DataTable :table="table" :columns="columns" :rows="rows" :view-setting="viewSetting"
			@create-column="$emit('create-column')"
			@import="$emit('import')"
			@download-csv="$emit('download-csv')"
			@toggle-share="$emit('toggle-share')"
			@show-integration="$emit('show-integration')"
			@create-view="createView" />
	</div>
</template>

<script>
import ElementDescription from './ElementDescription.vue'
import Dashboard from './Dashboard.vue'
import DataTable from './DataTable.vue'

import { emit } from '@nextcloud/event-bus'

export default {
	components: {
		ElementDescription,
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
	methods: {
		createView() {
			emit('tables:view:create', this.table.id)
		},
	},
}
</script>
