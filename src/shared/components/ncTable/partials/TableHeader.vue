<template>
	<tr>
		<th>
			<div class="cell-wrapper">
				<NcCheckboxRadioSwitch :checked="allRowsAreSelected" @update:checked="value => $emit('select-all-rows', value)" />
				<div v-if="hasRightHiddenNeighbor(-1)" class="hidden-indicator-first" @click="unhide(-1)" />
			</div>
		</th>
		<th v-for="col in visibleColumns" :key="col.id">
			<div class="cell-wrapper">
				<div class="cell-options-wrapper">
					<div class="cell">
						<div class="clickable" @click="updateOpenState(col.id)">
							{{ col.title }}
						</div>
						<TableHeaderColumnOptions
							:column="col"
							:open-state.sync="openedColumnHeaderMenus[col.id]"
							:can-hide="visibleColumns.length > 1"
							:element="element"
							:config="config"
							@add-filter="filter => $emit('add-filter', filter)" />
					</div>
					<div v-if="getFilterForColumn(col)" class="filter-wrapper">
						<FilterLabel v-for="filter in getFilterForColumn(col)"
							:id="filter.columnId + filter.operator.id+ filter.value"
							:key="filter.columnId + filter.operator.id+ filter.value"
							:operator="filter.operator"
							:value="filter.value"
							@delete-filter="id => $emit('delete-filter', id)" />
					</div>
				</div>
				<div v-if="hasRightHiddenNeighbor(col.id)" class="hidden-indicator" @click="unhide(col.id)" />
			</div>
		</th>
		<th data-cy="customTableAction">
			<slot name="actions" />
		</th>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import TableHeaderColumnOptions from './TableHeaderColumnOptions.vue'
import FilterLabel from './FilterLabel.vue'
import { mapGetters } from 'vuex'

export default {

	components: {
		FilterLabel,
		NcCheckboxRadioSwitch,
		TableHeaderColumnOptions,
	},

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
		element: {
			type: Object,
			default: () => {},
		},
		viewSetting: {
			type: Object,
			default: null,
		},
		config: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			openedColumnHeaderMenus: {},
		}
	},

	computed: {
		...mapGetters(['activeView']),
		allRowsAreSelected() {
			if (Array.isArray(this.rows) && Array.isArray(this.selectedRows) && this.rows.length !== 0) {
				return this.rows.length === this.selectedRows.length
			} else {
				return false
			}
		},
		visibleColumns() {
			return this.columns.filter(col => !this.viewSetting?.hiddenColumns?.includes(col.id))
		},
	},

	methods: {
		updateOpenState(columnId) {
			this.openedColumnHeaderMenus[columnId] = !this.openedColumnHeaderMenus[columnId]
			this.openedColumnHeaderMenus = Object.assign({}, this.openedColumnHeaderMenus)
		},
		getFilterForColumn(column) {
			return this.viewSetting?.filter?.filter(item => item.columnId === column.id)
		},
		hasRightHiddenNeighbor(colId) {
			return this.viewSetting?.hiddenColumns?.includes(this.columns[this.columns.indexOf(this.columns.find(col => col.id === colId)) + 1]?.id)
		},
		unhide(colId) {
			this.$store.dispatch('unhideColumn', { columnId: this.columns[this.columns.indexOf(this.columns.find(col => col.id === colId)) + 1]?.id })
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
	margin-top: calc(var(--default-grid-baseline) * -1);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
	display: flex;
	flex-wrap: wrap;
	gap: 0 calc(var(--default-grid-baseline) * 2);
}

:deep(.checkbox-radio-switch__icon) {
	margin: 0;
}

.clickable {
	cursor: pointer;
}

.hidden-indicator {
	border-right: solid;
	border-color: var(--color-primary);
	border-width: 3px;
	padding-left: calc(var(--default-grid-baseline) * 1);
	cursor: pointer;
}

.hidden-indicator-first {
	border-right: solid;
	border-color: var(--color-primary);
	border-width: 3px;
	padding-left: calc(var(--default-grid-baseline) * 4);
	cursor: pointer;
}

.cell-wrapper {
	display: flex;
	justify-content: space-between;
}

.cell-options-wrapper {
	display: flex;
	flex-direction: column;
	width: 100%;
}

</style>
