<!--

This is a reusable component. There are no outside dependency's.

Emitted events
==============
edit-row                -> click on edit button in a row
update-selected-rows    -> send an array with selected row IDs
create-row              -> click on create (plus) button
create-column
edit-columns
delete-selected-rows

Props
=====
rows <array>            -> Array with row-objects { "columnId": 1, "value": "some" }
columns <array>         -> Array with column-objects { "id":2, "tableId":1, "title":"Description", ... }
config                  -> config object for the table
  options
    show-create-row [true]
    show-delete-rows [true]
  table
    rows
      show-delete-button [false]
      show-edit-button [true]
      action-buttons-position [right]
      action-button-sticky [true]
    columns
      show-inline-edit-button [true]

-->

<template>
	<div class="NcTable">
		<div class="options row" style="padding-right: calc(var(--default-grid-baseline) * 2);">
			<Options :rows="rows"
				:selected-rows="selectedRows"
				:show-options="columns.length !== 0"
				:table="table"
				@create-row="$emit('create-row')"
				@download-csv="data => downloadCsv(data, columns, table)"
				@delete-selected-rows="rowIds => $emit('delete-selected-rows', rowIds)" />
		</div>
		<div class="custom-table row">
			<CustomTable v-if="canReadTable(table)"
				:columns="columns"
				:rows="rows"
				:table="table"
				@create-row="$emit('create-row')"
				@edit-row="rowId => $emit('edit-row', rowId)"
				@create-column="$emit('create-column')"
				@edit-columns="$emit('edit-columns')"
				@update-selected-rows="rowIds => selectedRows = rowIds"
				@download-csv="data => downloadCsv(data, columns, table)" />
			<NcEmptyContent v-else
				:title="t('tables', 'Create rows')"
				:description="t('tables', 'You are not allowed to read this table, but you can still create rows.')">
				<template #icon>
					<Plus :size="25" />
				</template>
				<template #action>
					<NcButton :aria-label="t('table', 'Create row')" type="primary" @click="$emit('create-row')">
						<template #icon>
							<Plus :size="25" />
						</template>
						{{ t('table', 'Create row') }}
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

export default {
	name: 'NcTable',

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
	},

	data() {
		return {
			selectedRows: [],
		}
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
