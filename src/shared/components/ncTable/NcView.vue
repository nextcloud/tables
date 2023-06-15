<template>
	<div class="NcTable">
		<div class="options row" style="padding-right: calc(var(--default-grid-baseline) * 2);">
			<Options :rows="rows"
				:columns="columns"
				:selected-rows="selectedRows"
				:show-options="columns.length !== 0"
				:table="table"
				:view="view"
				@create-row="$emit('create-row')"
				@download-csv="data => downloadCsv(data, columns, table)"
				@add-filter="filter => $emit('add-filter', filter)"
				@set-search-string="str => $emit('set-search-string', str)"
				@delete-selected-rows="rowIds => $emit('delete-selected-rows', rowIds)" />
		</div>
		<div class="custom-table row">
			<CustomTable v-if="canReadTable(table)"
				:columns="columns"
				:rows="rows"
				:table="table"
				:view="view"
				@create-row="$emit('create-row')"
				@import="table => $emit('import', table)"
				@edit-row="rowId => $emit('edit-row', rowId)"
				@create-column="$emit('create-column')"
				@edit-columns="$emit('edit-columns')"
				@add-filter="filter => $emit('add-filter', filter)"
				@update-selected-rows="rowIds => selectedRows = rowIds"
				@download-csv="data => downloadCsv(data, columns, table)"
				@delete-filter="id => $emit('delete-filter', id)" />
			<NcEmptyContent v-else
				:title="t('tables', 'Create rows')"
				:description="t('tables', 'You are not allowed to read this table, but you can still create rows.')">
				<template #icon>
					<Plus :size="25" />
				</template>
				<template #action>
					<NcButton :aria-label="t('tables', 'Create row')" type="primary" @click="$emit('create-row')">
						<template #icon>
							<Plus :size="25" />
						</template>
						{{ t('tables', 'Create row') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>

import Options from './sections/Options.vue'
import CustomTable from './sections/CustomTable.vue'
import exportTableMixin from './mixins/exportTableMixin.js'
import permissionsMixin from './mixins/permissionsMixin.js'
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
export default {
	name: 'NcView',

	components: { CustomTable, Options, NcButton, NcEmptyContent, Plus },

	mixins: [exportTableMixin, permissionsMixin],

	props: {
		rows: {
			type: Array,
			default: () => [],
		},
		columns: {
			type: Array,
			default: () => [],
		},
		table: {
			type: Object,
			default: () => {},
		},
		view: {
		      type: Object,
		      default: null,
		    },
	},
	data() {
		return {
			selectedRows: [],
		}
	},

	mounted() {
		subscribe('tables:selected-rows:deselect', this.deselectRows)
		console.debug("Build View: Columns: ",this.columns,"Rows:", this.rows)
	},
	beforeDestroy() {
		unsubscribe('tables:selected-rows:deselect', this.deselectRows)
	},
	methods: {
		deselectRows() {
			this.selectedRows = []
		},
	},
}
</script>

<style scoped lang="scss">

.options.row {
	position: sticky;
	top: 52px;
	left: 0;
	z-index: 15;
	background-color: var(--color-main-background-translucent);
	padding-top: 4px; // fix to show buttons completely
	padding-bottom: 4px; // to make it nice with the padding-top
}
</style>
