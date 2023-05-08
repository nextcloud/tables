<template>
	<tr v-if="row" :class="{ selected }">
		<td><NcCheckboxRadioSwitch :checked="selected" @update:checked="v => $emit('update-row-selection', { rowId: row.id, value: v })" /></td>
		<td v-for="col in columns" :key="col.id" :class="{ 'search-result': getCell(col.id)?.searchStringFound, 'filter-result': getCell(col.id)?.filterFound }">
			<TableCellProgress v-if="col.type === 'number' && col.subtype === 'progress'"
				:column="col"
				:row-id="row.id"
				:value="parseInt(getCellValue(col.id, false))" />
			<TableCellLink v-else-if="col.type === 'text' && col.subtype === 'link'"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id)" />
			<TableCellNumber v-else-if="col.type === 'number' && !col.subtype"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id)" />
			<TableCellStars v-else-if="col.type === 'number' && col.subtype === 'stars'"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id)" />
			<TableCellYesNo v-else-if="col.type === 'selection' && col.subtype === 'check'"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id) === 'true'" />
			<TableCellSelection v-else-if="col.type === 'selection' && !col.subtype"
				:column="col"
				:row-id="row.id"
				:value="parseInt(getCellValue(col.id))" />
			<TableCellMultiSelection v-else-if="col.type === 'selection' && col.subtype === 'multi'"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id, false)" />
			<TableCellDateTime v-else-if="col.type === 'datetime'"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id)" />
			<TableCellTextLine v-else-if="col.type === 'text' && col.subtype === 'line'"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id)" />
			<TableCellTextRich v-else-if="col.type === 'text' && col.subtype === 'rich'"
				:column="col"
				:row-id="row.id"
				:value="getCellValue(col.id, false)" />
			<TableCellHtml v-else
				:value="getCellValue(col.id)"
				:row-id="row.id"
				:column="col" />
		</td>
		<td>
			<NcButton type="primary" :aria-label="t('tables', 'Edit row')" @click="$emit('edit-row', row.id)">
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
	},
	computed: {
		getSelection: {
			get: () => { return this.selected },
			set: () => { alert('updating selection') },
		},
	},
	methods: {
		getCell(columnId) {
			return this.row.data.find(item => item.columnId === columnId) || null
		},
		getCellValue(columnId, loadDefault = true) {
			if (!this.row) {
				return null
			}

			// lets see if we have a value
			const cell = this.getCell(columnId)

			// if no value is given, try to get the default value from the column definition
			if (cell) {
				return cell.value
			} else if (!cell && loadDefault) {
				const column = this.columns.filter(column => column.id === columnId)[0]
				return column[column.type + 'Default']
			}
			return null
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

</style>
