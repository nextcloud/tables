<template>
	<div>
		<ElementDescription :active-element="table" :view-setting.sync="localViewSetting" />
		<Dashboard v-if="hasViews"
			:table="table"
			@create-column="$emit('create-column')"
			@import="$emit('import')"
			@toggle-share="$emit('toggle-share')"
			@show-integration="$emit('show-integration')"
			@create-view="createView" />
		<DataTable :table="table" :columns="columns" :rows="rows" :view-setting.sync="localViewSetting"
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
import { mapState } from 'vuex'

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

	data() {
		return {
			localViewSetting: this.viewSetting,
		}
	},
	computed: {
		...mapState(['views']),
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
			this.$store.commit('setActiveTableId', parseInt(this.table.id))
			emit('tables:view:create', { tableId: this.table.id, viewSetting: this.viewSetting.length > 0 ? this.viewSetting : this.localViewSetting })
		},
	},
}
</script>
