<template>
	<tr v-if="row" :class="{ selected }">
		<td><NcCheckboxRadioSwitch :checked="selected" @update:checked="v => $emit('update-row-selection', { rowId: row.id, value: v })" /></td>
		<td v-for="col in columns" :key="col.id">
			<TableCellProgress v-if="col.type === 'number' && col.subtype === 'progress'"
				:column="col"
				:row-id="row.id"
				:value="parseInt(getCellValue(col.id))" />
			<TableCellHtml v-else
				:value="getCellValue(col.id)"
				:row-id="row.id"
				:column="col" />
		</td>
		<td>
			<NcButton type="tertiary" @click="$emit('edit-row', row.id)">
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

export default {
	name: 'TableRow',
	components: {
		TableCellProgress,
		TableCellHtml,
		NcButton,
		Pencil,
		NcCheckboxRadioSwitch,
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
		getCellValue(columnId) {
			if (!this.row) {
				return null
			}

			const cell = this.row.data.find(item => item.columnId === columnId)
			return cell ? '' + cell.value : null
		},
	},
}
</script>

<style scoped>

tr.selected {
  background-color: var(--color-primary-light) !important;
}

</style>
