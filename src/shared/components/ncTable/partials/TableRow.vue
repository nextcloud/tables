<template>
	<tr v-if="row" :class="{ selected }">
		<td v-if="!readOnly" class="firstColumn">
			<NcCheckboxRadioSwitch :checked="selected" @update:checked="v => $emit('update-row-selection', { rowId: row.id, value: v })" />
		</td>
		<td v-if="selectRow" class="firstColumn">
			<NcCheckboxRadioSwitch :checked="selected" type="radio" @update:checked="v => $emit('update-row-selection', { rowId: row.id })" />
		</td>
		<td v-for="col in visibleColums" :key="col.id" :class="{ 'search-result': getCell(col.id)?.searchStringFound, 'filter-result': getCell(col.id)?.filterFound }">
			<component :is="getTableCell(col)"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col)" />
		</td>
		<td v-if="!readOnly" class="lastColumn">
			<NcButton v-if="canUpdateData(view) || canDeleteData(view)" type="primary" :aria-label="t('tables', 'Edit row')" @click="$emit('edit-row', row.id)">
				<template #icon>
					<Pencil :size="20" />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
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
import { ColumnTypes } from './../mixins/columnHandler.js'
import permissionsMixin from '../../../../shared/components/ncTable/mixins/permissionsMixin.js'

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
		Pencil,
		NcCheckboxRadioSwitch,
		TableCellDateTime,
		TableCellTextLine,
		TableCellSelection,
		TableCellMultiSelection,
		TableCellTextRich,
	},

	mixins: [permissionsMixin],
	props: {
		view: {
			type: Object,
			default: () => {},
		},
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
		readOnly: {
			type: Boolean,
			default: false,
		},
		selectRow: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		getSelection: {
			get: () => { return this.selected },
			set: () => { alert('updating selection') },
		},
		visibleColums() {
			return this.columns.filter(col => !this.viewSetting?.hiddenColumns?.includes(col.id))
		},
	},
	methods: {
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
			default: return 'TableCellHtml'
			}
		},
		getCell(columnId) {
			if (columnId < 0) {
				// See metaColumns.js for mapping
				let value
				switch (columnId) {
				case -1:
					value = this.row.id
					break
				case -2:
					value = this.row.createdBy
					break
				case -3:
					value = this.row.lastEditBy
					break
				case -4:
					value = this.row.createdAt
					break
				case -5:
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

			if ([ColumnTypes.NumberProgress, ColumnTypes.Selection].includes(column.type)) {
				return parseInt(value)
			}
			return value
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

.firstColumn {
		position: sticky;
		left: 0;
		padding-left: calc(var(--default-grid-baseline) * 4);
		padding-right: calc(var(--default-grid-baseline) * 4);
		width: 60px;
		background-color: var(--color-main-background);
		z-index: 5;
	}

.lastColumn {
	position: sticky;
	right: 0;
	width: 55px;
	background-color: inherit;
	padding-right: 16px;
	opacity: 0;
}
.lastColumn:hover {
	// visibility: visible;
	opacity: 1;
}

</style>
