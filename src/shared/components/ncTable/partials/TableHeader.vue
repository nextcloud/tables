<template>
	<tr>
		<th>
			<NcCheckboxRadioSwitch :checked="allRowsAreSelected" @update:checked="value => $emit('select-all-rows', value)" />
		</th>
		<th v-for="col in columns" :key="col.id">
			<div class="cell">
				{{ col.title }}
				<TableHeaderSort :column="col" />
			</div>
			<div v-if="getFilterForColumn(col)" class="filter-wrapper">
				<FilterLabel v-for="filter in getFilterForColumn(col)"
					:id="filter.columnId + filter.operator + filter.value"
					:key="filter.columnId + filter.operator + filter.value"
					:operator-label="getOperatorLabel(filter.operator)"
					:value="filter.value"
					@delete-filter="id => $emit('delete-filter', id)" />
			</div>
		</th>
		<th>
			<NcActions :force-menu="true">
				<NcActionButton v-if="!table.isShared || table.isShared && table.onSharePermissions.create"
					:close-after-click="true"
					icon="icon-add"
					@click="$emit('create-row')">
					{{ t('tables', 'Create row') }}
				</NcActionButton>
				<NcActionSeparator v-if="!table.isShared || table.isShared && table.onSharePermissions.create" />
				<NcActionButton v-if="!table.isShared || table.isShared && table.onSharePermissions.manage" :close-after-click="true" @click="$emit('create-column')">
					<template #icon>
						<TableColumnPlusAfter :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Create column') }}
				</NcActionButton>
				<NcActionButton v-if="!table.isShared || table.isShared && table.onSharePermissions.manage" :close-after-click="true" @click="$emit('edit-columns')">
					<template #icon>
						<TableEdit :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Edit columns') }}
				</NcActionButton>
				<NcActionSeparator v-if="!table.isShared || table.isShared && table.onSharePermissions.manage" />
				<NcActionButton v-if="!table.isShared || table.isShared && table.onSharePermissions.manage"
					:close-after-click="true"
					icon="icon-share"
					@click="toggleShare">
					{{ t('tables', 'Share') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true"
					icon="icon-download"
					@click="downloadCSV">
					{{ t('tables', 'Export as CSV') }}
				</NcActionButton>
			</NcActions>
		</th>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch, NcActions, NcActionButton, NcActionSeparator } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'
import TableEdit from 'vue-material-design-icons/TableEdit.vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import TableHeaderSort from './TableHeaderSort.vue'
import searchAndFilterMixin from '../mixins/searchAndFilterMixin.js'
import FilterLabel from './FilterLabel.vue'

export default {
	name: 'TableHeader',
	components: {
		FilterLabel,
		NcCheckboxRadioSwitch,
		TableHeaderSort,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		TableEdit,
		TableColumnPlusAfter,
	},
	mixins: [searchAndFilterMixin],
	props: {
		columns: {
			type: Array,
			default: () => [],
		},
		rows: {
			type: Array,
			default: () => [],
		},
		selectedRows: {
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
	computed: {
		allRowsAreSelected() {
			if (Array.isArray(this.rows) && Array.isArray(this.selectedRows) && this.rows.length !== 0) {
				return this.rows.length === this.selectedRows.length
			} else {
				return false
			}
		},
	},
	methods: {
		getFilterForColumn(column) {
			return this.view?.filter?.filter(item => item.columnId === column.id)
		},
		downloadCSV() {
			this.$emit('download-csv', this.rows)
		},
		toggleShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
		},
	},
}
</script>
<style lang="scss" scoped>

.cell {
	display: inline-flex;
	align-items: center;
}

.cell span {
	padding-left: 12px;

}

.filter-wrapper {
	margin-top: calc(var(--default-grid-baseline) * -2);
}

</style>
