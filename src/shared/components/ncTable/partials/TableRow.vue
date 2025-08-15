<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr v-if="row" :class="{ selected }">
		<td v-if="config.canSelectRows" :class="{sticky: config.canSelectRows}">
			<NcCheckboxRadioSwitch :checked="selected" @update:checked="v => $emit('update-row-selection', { rowId: row.id, value: v })" />
		</td>
		<td v-for="col in visibleColumns" :key="col.id"
			:style="getColumnWidthStyle(col)"
			:class="{
				'search-result': getCell(col.id)?.searchStringFound,
				'filter-result': getCell(col.id)?.filterFound,
				'fixed-width': col.customSettings?.width > 0
			}"
			@click="handleCellClick(col)">
			<component :is="getTableCell(col)"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col)"
				:element-id="elementId"
				:is-view="isView" />
		</td>
		<td v-if="config.showActions" :class="{sticky: config.showActions}">
			<NcButton v-if="config.canEditRows || config.canDeleteRows" type="primary" :aria-label="t('tables', 'Edit row')" data-cy="editRowBtn" @click="$emit('edit-row', row.id)">
				<template #icon>
					<Fullscreen :size="20" />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import Fullscreen from 'vue-material-design-icons/Fullscreen.vue'
import TableCellHtml from './TableCellHtml.vue'
import TableCellProgress from './TableCellProgress.vue'
import TableCellLink from './TableCellLink.vue'
import TableCellNumber from './TableCellNumber.vue'
import TableCellStars from './TableCellStars.vue'
import TableCellYesNo from './TableCellYesNo.vue'
import TableCellDateTime from './TableCellDateTime.vue'
import TableCellTextLine from './TableCellTextLine.vue'
import TableCellSelection from './TableCellSelection.vue'
import TableCellMultiSelection from './TableCellMultiSelection.vue'
import TableCellTextRich from './TableCellEditor.vue'
import TableCellUsergroup from './TableCellUsergroup.vue'
import { ColumnTypes, getColumnWidthStyle } from './../mixins/columnHandler.js'
import { translate as t } from '@nextcloud/l10n'
import {
	TYPE_META_ID, TYPE_META_CREATED_BY, TYPE_META_CREATED_AT, TYPE_META_UPDATED_BY, TYPE_META_UPDATED_AT,
} from '../../../../shared/constants.ts'
import activityMixin from '../../../mixins/activityMixin.js'

export default {
	name: 'TableRow',
	components: {
		TableCellYesNo,
		TableCellStars,
		TableCellNumber,
		TableCellLink,
		TableCellProgress,
		TableCellHtml,
		NcButton,
		Fullscreen,
		NcCheckboxRadioSwitch,
		TableCellDateTime,
		TableCellTextLine,
		TableCellSelection,
		TableCellMultiSelection,
		TableCellTextRich,
		TableCellUsergroup,
	},

	mixins: [activityMixin],
	props: {
		row: {
			type: Object,
			default: () => {},
		},
		columns: {
			type: Array,
			default: () => [],
		},
		selected: {
			type: Boolean,
			default: false,
		},
		viewSetting: {
			type: Object,
			default: null,
		},
		config: {
			type: Object,
			default: null,
		},
		elementId: {
			type: Number,
			default: null,
		},
		isView: {
			type: Boolean,
			default: true,
		},
	},
	computed: {
		getSelection: {
			get: () => { return this.selected },
			set: () => { alert('updating selection') },
		},
		visibleColumns() {
			return this.columns.filter(col => !this.viewSetting?.hiddenColumns?.includes(col.id))
		},
		// column types that don't support inline editing yet
		// to be used to trigger the edit modal instead of inline editing
		nonInlineEditableColumnTypes() {
			return [
			]
		},
	},
	methods: {
		t,
		getColumnWidthStyle,
		handleCellClick(column) {
			// If the column type doesn't support inline editing, trigger the edit modal
			if (this.nonInlineEditableColumnTypes.includes(column.type) && this.config.canEditRows) {
				this.$emit('edit-row', this.row.id)
			}
		},
		getTableCell(column) {
			switch (column.type) {
			case ColumnTypes.TextLine: return 'TableCellTextLine'
			case ColumnTypes.TextLink: return 'TableCellLink'
			case ColumnTypes.TextRich:return 'TableCellTextRich'
			case ColumnTypes.Number: return 'TableCellNumber'
			case ColumnTypes.NumberStars: return 'TableCellStars'
			case ColumnTypes.NumberProgress: return 'TableCellProgress'
			case ColumnTypes.Selection: return 'TableCellSelection'
			case ColumnTypes.SelectionMulti: return 'TableCellMultiSelection'
			case ColumnTypes.SelectionCheck: return 'TableCellYesNo'
			case ColumnTypes.Datetime: return 'TableCellDateTime'
			case ColumnTypes.DatetimeDate: return 'TableCellDateTime'
			case ColumnTypes.DatetimeTime: return 'TableCellDateTime'
			case ColumnTypes.Usergroup: return 'TableCellUsergroup'
			default: return 'TableCellHtml'
			}
		},
		getCell(columnId) {
			if (columnId < 0) {
				// See metaColumns.js for mapping
				let value
				switch (columnId) {
				case TYPE_META_ID:
					value = this.row.id
					break
				case TYPE_META_CREATED_BY:
					value = this.row.createdBy
					break
				case TYPE_META_UPDATED_BY:
					value = this.row.lastEditBy
					break
				case TYPE_META_CREATED_AT:
					value = this.row.createdAt
					break
				case TYPE_META_UPDATED_AT:
					value = this.row.lastEditAt
					break
				}
				return { columnId, value }
			}
			return this.row.data.find(item => item.columnId === columnId) || null
		},
		getCellValue(column) {
			if (!this.row) {
				return null
			}

			// lets see if we have a value
			const cell = this.getCell(column.id)
			let value

			if (cell) {
				value = cell.value
			} else {
				// if no value is given, try to get the default value from the column definition
				value = column.default()
			}

			return column.parseValue(value)
		},
		truncate(text) {
			if (text.length >= 400) {
				return text.substring(0, 400) + '...'
			} else {
				return text
			}
		},
	},
}
</script>

<style scoped lang="scss">

tr.selected {
	background-color: var(--color-primary-light) !important;
}

:deep(.search-result > div) {
	background-color: var(--color-primary-element-light);
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 3);
}

:deep(.checkbox-radio-switch__icon) {
	margin: 0;
}

td.fixed-width {
	overflow: hidden;
	white-space: normal;
}

</style>
