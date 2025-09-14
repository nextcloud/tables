<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr>
		<th v-if="config.canSelectRows" :class="{sticky: config.canSelectRows}">
			<div class="cell-wrapper">
				<NcCheckboxRadioSwitch :checked="allRowsAreSelected" @update:checked="value => $emit('select-all-rows', value)" />
				<div v-if="hasRightHiddenNeighbor(-1)" class="hidden-indicator-first" @click="unhide(-1)" />
			</div>
		</th>
		<th v-for="col in visibleColumns" :key="col.id" :style="getColumnWidthStyle(col)">
			<div class="cell-wrapper">
				<div class="cell-options-wrapper">
					<div class="cell">
						<div class="clickable" @click="updateOpenState(col.id)">
							{{ col.title }}
						</div>
						<TableHeaderColumnOptions
							:column="col"
							:open-state.sync="openedColumnHeaderMenus[col.id]"
							:config="config"
							:view-setting.sync="localViewSetting"
							@edit-column="col => $emit('edit-column', col)"
							@delete-column="col => $emit('delete-column', col)" />
					</div>
					<div v-if="getFilterForColumn(col)" class="filter-wrapper">
						<FilterLabel v-for="filter in getFilterForColumn(col)"
							:id="filter.columnId + filter.operator.id+ filter.value"
							:key="filter.columnId + filter.operator.id+ filter.value"
							:operator="castToFilter(filter.operator.id)"
							:value="filter.value"
							@delete-filter="id => deleteFilter(id)" />
					</div>
				</div>
				<div v-if="hasRightHiddenNeighbor(col.id)" class="hidden-indicator" @click="unhide(col.id)" />
			</div>
		</th>
		<th v-if="config.showActions" data-cy="customTableAction" :class="{sticky: config.showActions}">
			<slot name="actions" />
		</th>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import TableHeaderColumnOptions from './TableHeaderColumnOptions.vue'
import FilterLabel from './FilterLabel.vue'
import { getFilterWithId } from '../mixins/filter.js'
import { getColumnWidthStyle } from '../mixins/columnHandler.js'

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
			localViewSetting: this.viewSetting,
		}
	},

	computed: {
		allRowsAreSelected() {
			if (Array.isArray(this.rows) && Array.isArray(this.selectedRows) && this.rows.length !== 0) {
				return this.rows.length === this.selectedRows.length
			} else {
				return false
			}
		},
		visibleColumns() {
			return this.columns.filter(col => !this.localViewSetting?.hiddenColumns?.includes(col.id))
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
		getFilterWithId,
		getColumnWidthStyle,
		updateOpenState(columnId) {
			this.openedColumnHeaderMenus[columnId] = !this.openedColumnHeaderMenus[columnId]
			this.openedColumnHeaderMenus = Object.assign({}, this.openedColumnHeaderMenus)
		},
		getFilterForColumn(column) {
			return this.localViewSetting?.filter?.filter(item => item.columnId === column.id)
		},
		hasRightHiddenNeighbor(colId) {
			return this.localViewSetting?.hiddenColumns?.includes(this.columns[this.columns.indexOf(this.columns.find(col => col.id === colId)) + 1]?.id)
		},
		unhide(colId) {
			const index = this.localViewSetting.hiddenColumns.indexOf(this.columns[this.columns.indexOf(this.columns.find(col => col.id === colId)) + 1]?.id)
			if (index !== -1) {
				this.localViewSetting.hiddenColumns.splice(index, 1)
			}
		},
		deleteFilter(id) {
			const index = this.localViewSetting?.filter?.findIndex(item => item.columnId + item.operator.id + item.value === id)
			if (index !== -1) {
				this.localViewSetting.filter.splice(index, 1)
			}
		},
		castToFilter(operatorId) {
			return this.getFilterWithId(operatorId)
		},
	},
}
</script>
<style lang="scss" scoped>

th {
       white-space: normal;
}

.cell {
	display: inline-flex;
	align-items: center;
}

.cell span {
	padding-inline-start: 12px;

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
	border-inline-end: solid;
	border-color: var(--color-primary);
	border-width: 3px;
	padding-inline-start: calc(var(--default-grid-baseline) * 1);
	cursor: pointer;
}

.hidden-indicator-first {
	border-inline-end: solid;
	border-color: var(--color-primary);
	border-width: 3px;
	padding-inline-start: calc(var(--default-grid-baseline) * 4);
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
